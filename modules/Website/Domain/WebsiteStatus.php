<?php

namespace Modules\Website\Domain;

/**
 * Whether a tenant's site is live (spec §14).
 *
 * Two states only. A draft site 404s publicly and is visible to its owner through the authenticated
 * preview route; a published site serves `published_content`. Unpublishing moves back to Draft and
 * touches no content at all (invariant #4 — losing visibility never destroys records).
 */
enum WebsiteStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
