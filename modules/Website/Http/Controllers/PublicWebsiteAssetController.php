<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a tenant's uploaded branding image (§28, D-016) through Laravel instead of letting
 * Apache serve it directly off the `public/storage` symlink. On this host that symlinked path
 * answers every request with a bare Apache 403 regardless of file permissions — found live,
 * 2026-10-06, against the production `/storage/website/...` URLs `UploadWebsiteImage` used to
 * write. Reading the file through the `public` disk and returning it as a response needs no
 * symlink and never touches `storage/app/public` as a static Apache document root, so it is
 * immune to whatever in this host's config (FollowSymLinks, SymLinksIfOwnerMatch, or the
 * production-only root `.htaccess` that rewrites into `public/`) was refusing it.
 *
 * `{tenantId}`/`{filename}` are constrained by the route (`Routes/web.php`) to digits and a safe
 * filename charset, so this never becomes a path-traversal or arbitrary-disk-read primitive.
 */
final class PublicWebsiteAssetController
{
    public function __invoke(string $tenantId, string $filename): StreamedResponse
    {
        $path = 'website/'.$tenantId.'/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
