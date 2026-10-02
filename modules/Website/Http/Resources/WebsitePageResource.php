<?php

namespace Modules\Website\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Website\Models\WebsitePage;

/**
 * @property-read WebsitePage $resource
 */
final class WebsitePageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key->value,
            'label' => $this->resource->key->label(),
            'title' => $this->resource->title,
            'display_title' => $this->resource->displayTitle(),
            'is_enabled' => $this->resource->is_enabled,
            'is_mandatory' => $this->resource->key->isMandatory(),
            'sort' => $this->resource->sort,

            // The draft. The editor edits this; the public site never sees it.
            'content' => $this->resource->content ?? [],

            // Not the published content itself — only whether there is any — so the editor can show
            // "this page has unpublished changes" without shipping two copies of every page's copy
            // down the wire.
            'has_published_content' => $this->resource->published_content !== null,

            'seo_title' => $this->resource->seo_title,
            'seo_description' => $this->resource->seo_description,

            // What this page accepts, straight off the PageKey enum: the editor renders its form
            // from this rather than hard-coding a field list that would drift from the validator.
            'fields' => array_keys($this->resource->key->contentFields()),
            'lists' => collect($this->resource->key->contentLists())
                ->map(fn (array $definition): array => [
                    'max' => $definition['max'],
                    'fields' => array_keys($definition['rules']),
                ])
                ->all(),

            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
