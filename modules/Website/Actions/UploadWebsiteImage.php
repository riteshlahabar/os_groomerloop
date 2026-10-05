<?php

namespace Modules\Website\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $path = $file->storeAs(
            'website/'.$site->tenant_id,
            $field.'-'.Str::random(20).'.'.$file->extension(),
            'public',
        );

        $site->{$column} = Storage::disk('public')->url($path);
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

        $base = rtrim((string) config('filesystems.disks.public.url'), '/').'/';

        if (! str_starts_with($current, $base)) {
            return;
        }

        Storage::disk('public')->delete(Str::after($current, $base));
    }
}
