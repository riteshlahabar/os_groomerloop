<?php

namespace Modules\Website\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Website\Models\WebsitePage;

/**
 * Edit one page of the draft site: its title, whether it appears at all, its copy and its SEO
 * fields (spec §14).
 */
final class UpdateWebsitePage
{
    /**
     * @param  array<string, mixed>  $attributes  Validated page attributes, `content` included.
     */
    public function execute(WebsitePage $page, array $attributes): WebsitePage
    {
        return DB::transaction(function () use ($page, $attributes): WebsitePage {
            // Home has no off switch — a site whose entry point is disabled has no public URL at
            // all, and the one thing a §14 site must do is open. The request layer rejects the
            // attempt; this is the second line of defence for any other caller.
            if ($page->key->isMandatory()) {
                unset($attributes['is_enabled']);
            }

            // `content` is replaced wholesale rather than merged. The editor always submits the
            // whole page, and a merge would make it impossible to clear a field or delete a
            // gallery row — "send the key as null" would silently keep the old value.
            $page->fill($attributes)->save();

            return $page;
        });
    }
}
