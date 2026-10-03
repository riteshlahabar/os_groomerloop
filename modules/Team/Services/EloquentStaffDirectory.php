<?php

namespace Modules\Team\Services;

use App\Domain\DayOfWeek;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Team\Domain\StaffSummary;
use Modules\Team\Models\StaffMember;

final class EloquentStaffDirectory implements StaffDirectory
{
    /**
     * Memoised per request, with the rota and absences eager-loaded: the scheduler asks about the same
     * groomer once per candidate slot, and re-querying a week of shifts for each would make a calendar
     * render hundreds of queries.
     *
     * @var array<int, StaffMember|null>
     */
    private array $resolved = [];

    public function exists(int $staffMemberId): bool
    {
        return $this->model($staffMemberId) !== null;
    }

    public function find(int $staffMemberId): ?StaffSummary
    {
        $staff = $this->model($staffMemberId);

        return $staff === null ? null : $this->summarise($staff);
    }

    public function isAssignable(int $staffMemberId): bool
    {
        // Fails closed on an unknown staff member: a stale calendar must not be able to assign work to
        // someone this business does not have.
        return $this->model($staffMemberId)?->isAssignable() ?? false;
    }

    public function canPerform(int $staffMemberId, int $serviceId): bool
    {
        $staff = $this->model($staffMemberId);

        if ($staff === null || ! $staff->isAssignable()) {
            return false;
        }

        $assigned = $this->assignedServiceIds($staffMemberId);

        // No restrictions recorded means this groomer does everything — the common case, and the
        // reason a solo groomer never has to tick every service against their own name.
        if ($assigned === []) {
            return true;
        }

        return in_array($serviceId, $assigned, strict: true);
    }

    public function isAvailableAt(int $staffMemberId, DateTimeInterface $start, int $minutes): bool
    {
        $staff = $this->model($staffMemberId);

        if ($staff === null || ! $staff->isAssignable()) {
            return false;
        }

        $shifts = $staff->workingHours->where('day_of_week', DayOfWeek::fromDate($start));

        // No rota at all, or none on this day: not at work. The opposite default from a service's
        // rules, because a person with no shifts is not available — offering them would be worse.
        if ($shifts->isEmpty()) {
            return false;
        }

        $fitsAShift = $shifts->contains(
            fn ($shift): bool => $shift->accommodates($start->format('H:i'), $minutes)
        );

        if (! $fitsAShift) {
            return false;
        }

        $end = (new \DateTimeImmutable($start->format('Y-m-d H:i:s')))
            ->modify("+{$minutes} minutes");

        // On the rota, but away. Checked against the whole booking rather than its start, so a groom
        // running into a dentist appointment is refused.
        return ! $staff->timeOff->contains(
            fn ($absence): bool => $absence->overlaps($start, $end)
        );
    }

    /**
     * @return list<StaffSummary>
     */
    public function assignable(): array
    {
        return $this->summariseMany(
            StaffMember::query()->active()->with(['workingHours'])
        );
    }

    /**
     * @return list<StaffSummary>
     */
    public function bookableOnline(?int $serviceId = null): array
    {
        $staff = $this->summariseMany(
            StaffMember::query()->bookableOnline()->with(['workingHours'])
        );

        // Nobody with an empty rota, ever. `isAvailableAt()` below refuses every slot on every day
        // for such a person — "a person with no shifts is not available" — so offering them to a
        // customer is offering a dead end: §12's wizard would search its whole 14-day window, find
        // nothing on all 14 days, and tell the customer the *business* has no availability. That
        // exact misattribution was reported from production on 2026-10-03 against a salon whose
        // only published groomer had never been given working hours. `hasWorkingHours` exists on
        // the summary for precisely this distinction and was carried here unused.
        $staff = array_values(array_filter(
            $staff,
            static fn (StaffSummary $summary): bool => $summary->hasWorkingHours
        ));

        if ($serviceId === null) {
            return $staff;
        }

        // Filtered in PHP rather than SQL because "no restrictions means everything" is a rule about
        // the absence of rows, which a join cannot express without an awkward outer condition. The
        // list is a salon's team, not a table of thousands.
        return array_values(array_filter(
            $staff,
            fn (StaffSummary $summary): bool => $this->canPerform($summary->id, $serviceId)
        ));
    }

    /**
     * @param  list<int>  $staffMemberIds
     * @return array<int, string|null>
     */
    public function namesOf(array $staffMemberIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $staffMemberIds)));
        $unresolved = array_values(array_diff($ids, array_keys($this->resolved)));

        if ($unresolved !== []) {
            $found = StaffMember::query()
                ->with(['workingHours', 'timeOff'])
                ->whereKey($unresolved)
                ->get();

            foreach ($found as $staff) {
                $this->resolved[(int) $staff->getKey()] = $staff;
            }

            foreach ($unresolved as $id) {
                $this->resolved[$id] ??= null;
            }
        }

        $names = [];

        foreach ($ids as $id) {
            $names[$id] = $this->resolved[$id]?->display_name;
        }

        return $names;
    }

    public function findByUser(int $userId): ?StaffSummary
    {
        $staff = StaffMember::query()->with(['workingHours'])->where('user_id', $userId)->first();

        return $staff === null ? null : $this->summarise($staff);
    }

    public function hasAny(): bool
    {
        // active(), not every row: a business whose only groomer has left has nobody to book, and the
        // §7 checklist should say the step is outstanding.
        return StaffMember::query()->active()->exists();
    }

    public function lockForBooking(int $staffMemberId): void
    {
        StaffMember::query()->whereKey($staffMemberId)->lockForUpdate()->first();
    }

    /**
     * The eligibility pivot, read by id. Team owns this table (`D-017`) but never loads Catalog's
     * model — the ids are all Scheduling needs, and it validates them through `ServiceCatalog`.
     *
     * @return list<int>
     */
    private function assignedServiceIds(int $staffMemberId): array
    {
        return DB::table('staff_member_service')
            ->where('staff_member_id', $staffMemberId)
            ->pluck('service_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Tenant-scoped by the global scope, so an id from another business resolves to null here exactly
     * as it 404s over HTTP.
     */
    private function model(int $staffMemberId): ?StaffMember
    {
        return $this->resolved[$staffMemberId] ??= StaffMember::query()
            ->with(['workingHours', 'timeOff'])
            ->find($staffMemberId);
    }

    /**
     * @param  Builder<StaffMember>  $query
     * @return list<StaffSummary>
     */
    private function summariseMany($query): array
    {
        return $query->orderBy('position')
            ->orderBy('display_name')
            ->get()
            ->map(fn (StaffMember $staff): StaffSummary => $this->summarise($staff))
            ->all();
    }

    private function summarise(StaffMember $staff): StaffSummary
    {
        return new StaffSummary(
            id: (int) $staff->getKey(),
            displayName: $staff->display_name,
            jobTitle: $staff->job_title,
            bio: $staff->bio,
            isAssignable: $staff->isAssignable(),
            isBookableOnline: $staff->isPubliclyBookable(),
            userId: $staff->user_id === null ? null : (int) $staff->user_id,

            // From the loaded relation, so this does not fire a query per staff member in a list.
            hasWorkingHours: $staff->relationLoaded('workingHours')
                ? $staff->workingHours->isNotEmpty()
                : $staff->hasWorkingHours(),
        );
    }
}
