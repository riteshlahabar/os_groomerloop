<?php

namespace Modules\Website\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Website\Domain\WebsiteStatus;
use Modules\Website\Models\Website;

/**
 * Take the site offline again (spec §14).
 *
 * The snapshot columns are left exactly as they are: taking a site down must not destroy what was on
 * it (invariant #4), and re-publishing has to be one click rather than a retype. Only `status`
 * changes, so the public routes start answering 404 and the preview keeps working.
 */
final class UnpublishWebsite
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Website $site): Website
    {
        return DB::transaction(function () use ($site): Website {
            $site->status = WebsiteStatus::Draft;
            $site->save();

            $this->audit->record('website.unpublished', $site, [
                'published_at' => $site->published_at?->toIso8601String(),
            ]);

            return $site;
        });
    }
}
