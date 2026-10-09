<?php

namespace Modules\Website\Services;

use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Onboarding\Contracts\BusinessProfileDirectory;
use Modules\Scheduling\Contracts\OpeningHours;
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
        private readonly OpeningHours $hours,
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
            // Real opening hours, or none at all. The three templates all have an hours block in
            // their chrome, and the design bundle fills it with stock times — printing those on a
            // real business's public site would state a fact nobody entered, which is the same
            // thing invariant #7 forbids of a dashboard number. An unconfigured week answers `[]`
            // so a template renders nothing rather than seven "Closed" rows.
            openingHours: $this->hours->isUnset() ? [] : $this->hours->weekly(),
            // Never a second booking implementation: the §12 wizard is the only way to book
            // (invariant #2, D-023).
            bookingUrl: route('public-booking', ['tenant' => $tenant->slug]),
            // Customer Portal (D-043) login, id-keyed like the site itself (D-036) since
            // ResolveCustomerTenant resolves `{tenant}` by id, not slug.
            portalLoginUrl: route('customer-portal.login', ['tenant' => $tenant->id]),
            portalDashboardUrl: route('customer-portal.profile-page', ['tenant' => $tenant->id]),
            customerIsSignedIn: $this->customerIsSignedIn(),
            isPreview: $isPreview,
            isPageDisabled: $isPageDisabled,
        );
    }

    /**
     * Is this visitor signed in to *this* business's Customer Portal (`D-043`)?
     *
     * Deliberately only ever a boolean. The `customer` guard's provider is Crm's `Customer`, which
     * Website may not reach into (`D-007`) — so this asks the guard whether it has a user and never
     * touches the model, its columns or its relations. Nothing on this page needs the customer's
     * name: the header swaps one button's label and target, which a yes/no answers completely.
     *
     * Tenant correctness comes for free from ordering, the same way it does for `D-047`'s
     * signed-in booking, and it is worth naming because it is invisible: `Customer` carries the
     * `BelongsToTenant` global scope and the guard resolves its user lazily — here, inside the
     * composer — which is *after* this route's `ResolvePublicTenantById` has established the
     * tenant. A customer signed in to business A browsing business B's site is therefore looked up
     * under B's scope, is not found, and correctly sees the anonymous header.
     *
     * On the owner's draft preview this is normally false, because that page is authenticated on
     * the staff `web` guard and a staff login says nothing about the `customer` one — so a preview
     * shows the visitor's header, which is what a preview is for. The exception is a browser
     * holding both sessions at once, which then previews with "My Account"; left alone rather than
     * forced, since that browser genuinely is signed in as a customer and the preview is of
     * chrome, not of content.
     */
    private function customerIsSignedIn(): bool
    {
        return auth()->guard('customer')->check();
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
