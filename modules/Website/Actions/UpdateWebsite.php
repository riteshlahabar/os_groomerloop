<?php

namespace Modules\Website\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Website\Models\Website;

/**
 * Change the site's own settings — look, SEO, logo, accent colour, social links (spec §14).
 *
 * Only the draft is touched. A published site keeps serving its snapshot until
 * {@see PublishWebsite} runs again, which is what lets an owner redesign at lunchtime without their
 * customers watching it happen.
 */
final class UpdateWebsite
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Website $site, array $attributes): Website
    {
        return DB::transaction(function () use ($site, $attributes): Website {
            $before = $site->template_key->value;

            $site->fill($attributes)->save();

            // Only the template change is audited, not every keystroke of marketing copy: §27 wants
            // consequential actions in the trail, and a site's look is what someone would later ask
            // "who changed this" about. Publishing is audited separately.
            if ($site->template_key->value !== $before) {
                $this->audit->record('website.template_changed', $site, [
                    'from' => $before,
                    'to' => $site->template_key->value,
                ]);
            }

            return $site;
        });
    }
}
