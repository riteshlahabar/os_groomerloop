<?php

namespace Modules\Website\Domain;

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
     */
    public function __construct(
        public string $businessName,
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
        public bool $isPreview,
        public bool $isPageDisabled = false,
    ) {}

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
        return $this->setting('primary_color') ?? '#0f766e';
    }

    public function urlFor(PageKey $page): string
    {
        return $this->isPreview
            ? route('admin.website.preview', ['page' => $page->value])
            : route('website.public.page', ['tenant' => $this->tenantSlug, 'page' => $page->value]);
    }
}
