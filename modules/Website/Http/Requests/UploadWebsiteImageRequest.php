<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The first §28 upload surface in the product (`D-016`) — previously the owner could only paste
 * a URL for the logo and hero image. Authorisation is still the route's
 * (`permission:website.manage` + `entitlement:basic_website`), same reasoning
 * {@see UpdateWebsiteRequest} already documents.
 */
final class UploadWebsiteImageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 4096 KB (4 MB): generous enough for a real photo, small enough that one tenant's
            // branding assets cannot quietly become a storage problem. Not configurable per plan
            // — §28 has no tiered-storage concept yet, and this is one owner-uploaded image at a
            // time, not a gallery.
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }
}
