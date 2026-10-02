<?php

namespace Modules\Website\Services;

use Illuminate\Support\Facades\DB;
use Modules\Website\Domain\PageKey;
use Modules\Website\Models\Website;
use Modules\Website\Models\WebsitePage;

/**
 * Hands back the current tenant's site, creating it the first time anyone asks (spec §14).
 *
 * There is no "create a website" endpoint on purpose. Every tenant is entitled to `basic_website`
 * on every plan, so a site always conceptually exists; making the owner press New first would be a
 * step with no decision in it. The row is created lazily instead, on the first read, with every
 * PageKey present and disabled where it makes sense — so the editor renders a complete page list
 * immediately and the renderer never meets a missing page.
 */
final class WebsiteProvisioner
{
    /**
     * Pages a brand-new site starts with switched on. Gallery stays off until the owner has images
     * to put in it (§28 uploads do not exist yet — D-016 — so an empty gallery would publish an
     * empty page), and Team stays on because Team records already exist by the time a business
     * reaches §14 in the §7 checklist.
     *
     * @var list<string>
     */
    private const ENABLED_BY_DEFAULT = ['home', 'services', 'about', 'team', 'contact'];

    public function current(): Website
    {
        $site = Website::query()->with('pages')->first();

        if ($site !== null) {
            return $this->backfillPages($site);
        }

        return DB::transaction(function (): Website {
            // tenant_id is set by BelongsToTenant from the ambient tenant context, so this is
            // automatically the current tenant's site and cannot be anyone else's.
            $site = Website::query()->create([]);

            $this->createPages($site, PageKey::all());

            return $site->load('pages');
        });
    }

    /**
     * Adds rows for any PageKey introduced after this tenant's site was created.
     *
     * A new page case must not require a data migration or leave older tenants with a shorter
     * editor than newer ones.
     */
    private function backfillPages(Website $site): Website
    {
        $existing = $site->pages->map(fn (WebsitePage $page): string => $page->key->value)->all();

        $missing = array_values(array_filter(
            PageKey::all(),
            static fn (PageKey $key): bool => ! in_array($key->value, $existing, true),
        ));

        if ($missing === []) {
            return $site;
        }

        DB::transaction(fn () => $this->createPages($site, $missing));

        return $site->load('pages');
    }

    /**
     * @param  list<PageKey>  $keys
     */
    private function createPages(Website $site, array $keys): void
    {
        foreach ($keys as $key) {
            WebsitePage::query()->create([
                'website_id' => $site->getKey(),
                'key' => $key->value,
                'title' => $key->label(),
                'is_enabled' => $key->isMandatory() || in_array($key->value, self::ENABLED_BY_DEFAULT, true),
                'sort' => $key->sort(),
                'content' => [],
            ]);
        }
    }
}
