<?php

namespace Modules\Website\Services;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Onboarding\Contracts\BusinessProfileDirectory;
use Modules\Team\Contracts\StaffDirectory;
use Modules\Tenancy\Models\Tenant;
use Modules\Website\Domain\PageKey;
use Modules\Website\Domain\SiteView;
use Modules\Website\Domain\TemplateKey;
use Modules\Website\Models\Website;
use Modules\Website\Models\WebsitePage;

/**
 * Turns a tenant's stored site into one page's worth of render data (spec §14).
 *
 * Three rules live here, not in the views:
 *
 * 1. **What may be shown.** A draft site has no public pages; a disabled page has no public URL.
 *    Both answer null, and the caller turns that into a 404.
 * 2. **Where the live data comes from.** Services, groomers and the business's own details are read
 *    through Catalog's, Team's and Onboarding's contracts every render, never copied into website
 *    content — so a price change or a leaver is reflected immediately and a published site cannot
 *    advertise a service that was retired (D-007).
 * 3. **Draft versus live.** The public site reads `published_settings` / `published_content`; the
 *    owner's preview reads the draft columns. One class, one flag, so the two can never diverge in
 *    layout.
 */
final class SiteComposer
{
    public function __construct(
        private readonly ServiceCatalog $services,
        private readonly StaffDirectory $staff,
        private readonly BusinessProfileDirectory $profiles,
    ) {}

    /**
     * The published page a stranger asked for, or null when it may not be shown.
     */
    public function publicPage(Tenant $tenant, Website $site, PageKey $page): ?SiteView
    {
        $settings = $site->liveSettings();

        if ($settings === null) {
            return null;
        }

        $pages = $site->pages->filter(
            fn (WebsitePage $candidate): bool => $candidate->is_enabled,
        );

        $current = $pages->first(fn (WebsitePage $candidate): bool => $candidate->key === $page);

        if ($current === null) {
            return null;
        }

        return $this->compose(
            tenant: $tenant,
            settings: $settings,
            navPages: $pages->all(),
            current: $current,
            content: $current->liveContent(),
            isPreview: false,
        );
    }

    /**
     * The owner's own view of the draft, reachable only through the authenticated preview route.
     *
     * Every page is previewable, including a disabled one and a page of a site that has never been
     * published — that is the point of a preview. The flag lets the template say so.
     */
    public function previewPage(Tenant $tenant, Website $site, PageKey $page): ?SiteView
    {
        $current = $site->pages->first(fn (WebsitePage $candidate): bool => $candidate->key === $page);

        if ($current === null) {
            return null;
        }

        return $this->compose(
            tenant: $tenant,
            settings: $site->draftSettings(),
            navPages: $site->pages->filter(fn (WebsitePage $candidate): bool => $candidate->is_enabled)->all(),
            current: $current,
            content: $current->content ?? [],
            isPreview: true,
            isPageDisabled: ! $current->is_enabled,
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, WebsitePage>  $navPages
     * @param  array<string, mixed>  $content
     */
    private function compose(
        Tenant $tenant,
        array $settings,
        array $navPages,
        WebsitePage $current,
        array $content,
        bool $isPreview,
        bool $isPageDisabled = false,
    ): SiteView {
        $template = TemplateKey::tryFrom((string) ($settings['template_key'] ?? ''))
            ?? TemplateKey::Classic;

        $nav = [];

        foreach ($navPages as $page) {
            $nav[] = [
                'key' => $page->key->value,
                'label' => $page->displayTitle(),
                'url' => $isPreview
                    ? route('admin.website.preview', ['page' => $page->key->value])
                    : route('website.public.page', ['tenant' => $tenant->id, 'slug' => $tenant->slug, 'page' => $page->key->value]),
                'is_current' => $page->key === $current->key,
            ];
        }

        $businessName = $this->businessName($tenant);

        return new SiteView(
            businessName: $businessName,
            tenantId: $tenant->id,
            tenantSlug: (string) $tenant->slug,
            template: $template,
            page: $current->key,
            pageTitle: $current->displayTitle(),
            seoTitle: $current->seo_title ?? ($settings['seo_title'] ?? null),
            seoDescription: $current->seo_description ?? ($settings['seo_description'] ?? null),
            settings: $settings,
            content: $content,
            nav: $nav,
            // Only what a stranger may be offered: active, online-visible, not an add-on.
            services: $this->services->bookableOnline(),
            staff: $this->staff->bookableOnline(),
            profile: $this->profiles->summary(),
            // Never a second booking implementation: the §12 wizard is the only way to book
            // (invariant #2, D-023).
            bookingUrl: route('public-booking', ['tenant' => $tenant->slug]),
            isPreview: $isPreview,
            isPageDisabled: $isPageDisabled,
        );
    }

    /**
     * The name to put in the header: the legal name from §7 step 2 when it exists, else the tenant's
     * own name, which always does.
     */
    private function businessName(Tenant $tenant): string
    {
        $profile = $this->profiles->summary();

        if ($profile !== null && filled($profile->legalName)) {
            return (string) $profile->legalName;
        }

        return (string) $tenant->name;
    }
}
