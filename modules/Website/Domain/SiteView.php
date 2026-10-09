<?php

namespace Modules\Website\Domain;

use App\Domain\DayOfWeek;
use Modules\Catalog\Domain\ServiceSummary;
use Modules\Onboarding\Domain\BusinessProfileSummary;
use Modules\Team\Domain\StaffSummary;
use Modules\Website\Services\SiteComposer;

/**
 * Everything one render of one tenant-site page needs (spec §14).
 *
 * Assembled by {@see SiteComposer} and handed to a Blade template, so a
 * template never queries anything, never decides whether a page may be shown, and cannot reach a
 * model: it receives readonly summaries and arrays and lays them out. That keeps the three
 * templates interchangeable and keeps the module-boundary rule (D-007) true inside views as well as
 * inside PHP.
 *
 * @property-read array<string, mixed> $settings
 */
final readonly class SiteView
{
    /**
     * @param  array<string, mixed>  $settings  Site-level settings: template, SEO, logo, colour, social.
     * @param  array<string, mixed>  $content  The current page's content fields.
     * @param  list<array{key: string, label: string, url: string, is_current: bool}>  $nav
     * @param  list<ServiceSummary>  $services  Online-bookable services, live from Catalog.
     * @param  list<StaffSummary>  $staff  Publishable groomers, live from Team.
     * @param  array<int, list<array{starts_at: string, ends_at: string}>>  $openingHours  ISO day => windows, or `[]` when the business has never set any.
     */
    public function __construct(
        public string $businessName,
        public int $tenantId,
        public string $tenantSlug,
        public TemplateKey $template,
        public PageKey $page,
        public string $pageTitle,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public array $settings,
        public array $content,
        public array $nav,
        public array $services,
        public array $staff,
        public ?BusinessProfileSummary $profile,
        public string $bookingUrl,
        public string $portalLoginUrl,
        public string $portalDashboardUrl,
        /*
         * Is the person looking at this page signed in to this business's Customer Portal
         * (`D-043`)? The three headers swap their "Login" button for "My Account" on it — before
         * this, an already-signed-in customer was shown "Login", clicked it, and was asked for
         * credentials they had already given.
         *
         * This makes a page that is otherwise identical for every visitor vary by session, which
         * was checked rather than assumed: the response already carries `Cache-Control: no-cache,
         * private` (the session middleware's own doing), so there is nothing in front of it that
         * could serve one customer's header to another. Crawlability — the whole reason §14 is
         * server-rendered (`D-030`) — is untouched: a crawler has no session, so it sees exactly
         * the anonymous page it saw before, and no *content* depends on this flag, only which of
         * two buttons the header draws.
         */
        public bool $customerIsSignedIn,
        public bool $isPreview,
        public array $openingHours = [],
        public bool $isPageDisabled = false,
    ) {}

    /**
     * Opening hours collapsed into the "MON - FRI 9:30 AM - 7:30 PM" shape the three templates'
     * chrome is designed around: consecutive days whose windows are identical become one row.
     *
     * Done here rather than in each template because all three need the same grouping and none of
     * them may contain logic. Returns `[]` when no hours are set, so a template renders no hours
     * block at all — see the composer's note on why stock times are not an option.
     *
     * @return list<array{days: string, hours: string}>
     */
    public function openingHoursSummary(): array
    {
        if ($this->openingHours === []) {
            return [];
        }

        $rows = [];

        foreach (DayOfWeek::cases() as $day) {
            $windows = $this->openingHours[$day->value] ?? [];

            $hours = $windows === []
                ? 'Closed'
                : implode(', ', array_map(
                    static fn (array $w): string => self::clock($w['starts_at']).' - '.self::clock($w['ends_at']),
                    $windows,
                ));

            $last = $rows === [] ? null : $rows[count($rows) - 1];

            if ($last !== null && $last['hours'] === $hours) {
                $rows[count($rows) - 1]['last'] = $day;

                continue;
            }

            $rows[] = ['first' => $day, 'last' => $day, 'hours' => $hours];
        }

        return array_values(array_map(
            static fn (array $row): array => [
                'days' => $row['first'] === $row['last']
                    ? strtoupper($row['first']->abbreviation())
                    : strtoupper($row['first']->abbreviation()).' - '.strtoupper($row['last']->abbreviation()),
                'hours' => $row['hours'],
            ],
            $rows,
        ));
    }

    /**
     * `09:30` as `9:30 AM`. The stored value is tenant-local wall clock, so this is pure string
     * work — never a Date/Carbon round-trip, which would shift the hour by the server's offset
     * (CLAUDE.md's wall-clock trap).
     */
    private static function clock(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) !== 1) {
            return $time;
        }

        $hour = (int) $m[1];
        $suffix = $hour < 12 ? 'AM' : 'PM';
        $display = $hour % 12;

        return ($display === 0 ? 12 : $display).':'.$m[2].' '.$suffix;
    }

    /**
     * A content field, with a fallback for the common "owner left it blank" case.
     */
    public function text(string $field, ?string $default = null): ?string
    {
        $value = $this->content[$field] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * A repeatable content block (gallery images, home highlights), always a list.
     *
     * Rows that are entirely blank are dropped here rather than in each template: an owner who
     * added three highlight slots and filled one should get one, not one and two empty cards.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $field): array
    {
        $rows = $this->content[$field] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $filled = array_filter($row, static fn ($value): bool => is_string($value) ? trim($value) !== '' : $value !== null);

            if ($filled !== []) {
                $clean[] = $row;
            }
        }

        return $clean;
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        $value = $this->settings[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    /**
     * Social links the owner filled in, as handle => url.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        $social = $this->settings['social'] ?? [];

        if (! is_array($social)) {
            return [];
        }

        return array_filter(
            $social,
            static fn ($url): bool => is_string($url) && trim($url) !== '',
        );
    }

    /**
     * The accent colour, falling back to the template's own so a site always renders finished.
     */
    public function primaryColor(): string
    {
        return $this->setting('primary_color') ?? '#c25414';
    }

    /**
     * The accent as `r,g,b`, because the template stylesheet carries both `--primary` and
     * `--primary-rgb` and uses the latter inside `rgba()` for every tint and shadow. Overriding
     * only the hex leaves every translucent accent still painted in the bundle's default orange.
     */
    public function primaryColorRgb(): string
    {
        $hex = ltrim($this->primaryColor(), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return '194,84,20';
        }

        return implode(',', [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ]);
    }

    public function urlFor(PageKey $page): string
    {
        return $this->isPreview
            ? route('admin.website.preview', ['page' => $page->value])
            : route('website.public.page', ['tenant' => $this->tenantId, 'slug' => $this->tenantSlug, 'page' => $page->value]);
    }
}
