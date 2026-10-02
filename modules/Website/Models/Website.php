<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tenancy\Concerns\BelongsToTenant;
use Modules\Website\Actions\PublishWebsite;
use Modules\Website\Domain\TemplateKey;
use Modules\Website\Domain\WebsiteStatus;

/**
 * A tenant's public site (spec §14, §26).
 *
 * @property TemplateKey $template_key
 * @property WebsiteStatus $status
 * @property array<string, mixed>|null $social
 * @property array<string, mixed>|null $published_settings
 */
final class Website extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'template_key',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'logo_url',
        'hero_image_url',
        'primary_color',
        'social',
        'published_settings',
    ];

    /**
     * Mirrors the migration's defaults so a freshly made site answers correctly before it is
     * re-read from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'template_key' => 'classic',
        'status' => 'draft',
    ];

    /**
     * @return HasMany<WebsitePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(WebsitePage::class)->orderBy('sort');
    }

    /**
     * The draft settings, shaped exactly like `published_settings`.
     *
     * One method so publishing cannot drift from rendering: {@see PublishWebsite}
     * stores what this returns, and the public renderer reads the stored copy.
     *
     * @return array<string, mixed>
     */
    public function draftSettings(): array
    {
        return [
            'template_key' => $this->template_key->value,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'logo_url' => $this->logo_url,
            'hero_image_url' => $this->hero_image_url,
            'primary_color' => $this->primary_color,
            'social' => $this->social ?? [],
        ];
    }

    /**
     * What the public site renders from: the published snapshot, or null while it is a draft.
     *
     * @return array<string, mixed>|null
     */
    public function liveSettings(): ?array
    {
        if (! $this->status->isPublished()) {
            return null;
        }

        // A site published before this column was populated would otherwise render with no
        // settings at all; falling back to the draft is the lesser of the two wrongs and cannot
        // leak anything, since publishing is what put the draft live in the first place.
        return $this->published_settings ?? $this->draftSettings();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_key' => TemplateKey::class,
            'status' => WebsiteStatus::class,
            'published_at' => 'datetime',
            'social' => 'array',
            'published_settings' => 'array',
        ];
    }
}
