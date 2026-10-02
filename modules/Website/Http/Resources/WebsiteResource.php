<?php

namespace Modules\Website\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Website\Domain\TemplateKey;
use Modules\Website\Models\Website;

/**
 * @property-read Website $resource
 */
final class WebsiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Tenancy is shared kernel, so reading the tenant here is allowed (D-007) — but
        // Model::shouldBeStrict() is on outside production, so the relation must be loaded
        // explicitly rather than lazily touched in a property read.
        $this->resource->loadMissing('tenant');
        $slug = (string) $this->resource->tenant->slug;

        return [
            'template_key' => $this->resource->template_key->value,
            'template_label' => $this->resource->template_key->label(),
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'is_published' => $this->resource->status->isPublished(),
            'published_at' => $this->resource->published_at?->toIso8601String(),

            'seo_title' => $this->resource->seo_title,
            'seo_description' => $this->resource->seo_description,
            'logo_url' => $this->resource->logo_url,
            'hero_image_url' => $this->resource->hero_image_url,
            'primary_color' => $this->resource->primary_color,
            'social' => $this->resource->social ?? [],

            // Absolute, because the owner's whole reason for opening this screen is to copy the link
            // and hand it to a customer.
            'public_url' => route('website.public.home', ['tenant' => $slug]),
            'preview_url' => route('admin.website.preview', ['page' => 'home']),
            'booking_url' => route('public-booking', ['tenant' => $slug]),

            'pages' => WebsitePageResource::collection($this->resource->pages),

            // The template catalogue travels with the site so the picker needs no second request and
            // cannot list a look the backend does not render.
            'templates' => collect(TemplateKey::all())
                ->map(fn (TemplateKey $key): array => [
                    'key' => $key->value,
                    'label' => $key->label(),
                    'description' => $key->description(),
                ])
                ->all(),
        ];
    }
}
