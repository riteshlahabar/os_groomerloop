<?php

namespace Modules\Website\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Website\Domain\WebsiteStatus;
use Modules\Website\Models\Website;
use Modules\Website\Models\WebsitePage;

/**
 * Put the draft live (spec §14).
 *
 * Publishing is a copy, not a flag: the draft settings and every enabled page's content are snapshotted
 * into the `published_*` columns, and the public renderer reads only those. That is what makes an
 * unfinished edit invisible, makes "what is actually live right now" answerable, and makes unpublish
 * non-destructive — the snapshot simply stops being served (invariant #4).
 *
 * Services, groomers and the business's contact details are deliberately NOT snapshotted. They are
 * read live through their owning modules' contracts on every render, so a published site never
 * advertises a retired service or a groomer who has left.
 */
final class PublishWebsite
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Website $site): Website
    {
        return DB::transaction(function () use ($site): Website {
            $site->pages->each(function (WebsitePage $page): void {
                $page->published_content = $page->content ?? [];
                $page->save();
            });

            $wasPublished = $site->status->isPublished();

            $site->published_settings = $site->draftSettings();
            $site->status = WebsiteStatus::Published;
            $site->published_at = now();
            $site->save();

            $this->audit->record($wasPublished ? 'website.republished' : 'website.published', $site, [
                'template' => $site->template_key->value,
                'pages' => $site->pages
                    ->filter(fn (WebsitePage $page): bool => $page->is_enabled)
                    ->map(fn (WebsitePage $page): string => $page->key->value)
                    ->values()
                    ->all(),
            ]);

            return $site->load('pages');
        });
    }
}
