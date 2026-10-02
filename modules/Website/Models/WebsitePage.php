<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Concerns\BelongsToTenant;
use Modules\Website\Domain\PageKey;

/**
 * One page of a tenant site (spec §14).
 *
 * @property PageKey $key
 * @property array<string, mixed>|null $content
 * @property array<string, mixed>|null $published_content
 */
final class WebsitePage extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'website_id',
        'key',
        'title',
        'is_enabled',
        'sort',
        'content',
        'published_content',
        'seo_title',
        'seo_description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'sort' => 0,
    ];

    /**
     * @return BelongsTo<Website, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * The heading the public navigation shows: whatever the owner typed, else the page's own name.
     */
    public function displayTitle(): string
    {
        return filled($this->title) ? (string) $this->title : $this->key->label();
    }

    /**
     * Live content, with the draft used only as a fallback for a page published before snapshots
     * existed. Never reads the draft for an unpublished page — that is the renderer's job to refuse.
     *
     * @return array<string, mixed>
     */
    public function liveContent(): array
    {
        return $this->published_content ?? $this->content ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PageKey::class,
            'is_enabled' => 'boolean',
            'content' => 'array',
            'published_content' => 'array',
        ];
    }
}
