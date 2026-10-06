<?php

namespace Modules\Website\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Website\Models\Website;

/**
 * The §28 upload path for the two §14 branding images (`D-016`) — logo and hero photo, which
 * until now only accepted a pasted URL (`UpdateWebsiteRequest`). Pet photos and gallery images
 * are the other named consumers of §28 and are not built here; this is scoped to the two fields
 * the owner actually asked for.
 *
 * Stored on the `public` disk under `website/{tenant_id}/`, so one tenant's assets cannot be
 * reached or overwritten by guessing another tenant's path — the tenant id in the path is the
 * same isolation every other tenant-owned query gets from `BelongsToTenant`, just expressed in a
 * filesystem path instead of a `WHERE tenant_id = ?` clause, because a public disk has no query
 * scope to enforce it for us.
 *
 * The saved column holds a `website-assets/...` URL (PublicWebsiteAssetController), not the
 * public disk's own `Storage::url()` — found live, 2026-10-06, that this host serves
 * `/storage/...` (the `public/storage` symlink) as a bare Apache 403 no matter the file
 * permissions, so every uploaded image 404'd/403'd in the browser despite the upload itself
 * succeeding. Routing the read through Laravel sidesteps that host's symlink handling entirely.
 */
final class UploadWebsiteImage
{
    /**
     * Route segment => the column it writes. Closed set, not a free-form field name, so nothing
     * ever writes to an arbitrary column on this model from a URL segment.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'logo' => 'logo_url',
        'hero' => 'hero_image_url',
    ];

    public function execute(Website $site, string $field, UploadedFile $file): Website
    {
        $column = $this->columnFor($field);

        $this->deleteExistingUpload($site, $column);

        $filename = $field.'-'.Str::random(20).'.'.$file->extension();

        $file->storeAs('website/'.$site->tenant_id, $filename, 'public');

        $site->{$column} = URL::route('website.asset.show', [
            'tenantId' => $site->tenant_id,
            'filename' => $filename,
        ]);
        $site->save();

        return $site;
    }

    public function remove(Website $site, string $field): Website
    {
        $column = $this->columnFor($field);

        $this->deleteExistingUpload($site, $column);

        $site->{$column} = null;
        $site->save();

        return $site;
    }

    private function columnFor(string $field): string
    {
        return self::COLUMNS[$field] ?? throw new InvalidArgumentException("Unknown website image field \"{$field}\".");
    }

    /**
     * Removes the file backing the current value, but only when this disk actually owns it —
     * a value pasted as a plain URL before this feature existed (or any other external image)
     * is left alone, since deleting it would do nothing but break someone else's link.
     */
    private function deleteExistingUpload(Website $site, string $column): void
    {
        $current = $site->{$column};

        if ($current === null) {
            return;
        }

        $path = $this->diskPathFor($current, (int) $site->tenant_id);

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Resolves a stored URL back to its path on the `public` disk. Recognises both the current
     * `website.asset.show` route URL and the legacy `Storage::url()` form a value saved before
     * 2026-10-06 may still carry (that disk URL is what 403'd on this host — see the class
     * docblock). Anything else — a plain URL pasted before this feature existed, or any other
     * external image — resolves to null, so the caller leaves it alone.
     */
    private function diskPathFor(string $url, int $tenantId): ?string
    {
        $assetBase = rtrim(URL::to('website-assets/'.$tenantId), '/').'/';

        if (str_starts_with($url, $assetBase)) {
            return 'website/'.$tenantId.'/'.Str::after($url, $assetBase);
        }

        $diskBase = rtrim((string) config('filesystems.disks.public.url'), '/').'/';

        if (str_starts_with($url, $diskBase)) {
            return Str::after($url, $diskBase);
        }

        return null;
    }
}
