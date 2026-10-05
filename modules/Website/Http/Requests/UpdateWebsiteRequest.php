<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Website\Domain\TemplateKey;

/**
 * Site-level settings for the §14 editor.
 *
 * Authorisation is the route's: `permission:website.manage` plus `entitlement:basic_website`. A
 * request class that re-checked it would be the fourth place the same rule lives.
 */
final class UpdateWebsiteRequest extends FormRequest
{
    /**
     * The social networks a grooming business actually lists. A fixed set rather than free-form
     * key/value pairs, so a template can render an icon per network and nothing arbitrary reaches
     * the page.
     *
     * @var list<string>
     */
    public const SOCIAL_NETWORKS = ['facebook', 'instagram', 'google', 'tiktok', 'youtube', 'x'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'template_key' => ['sometimes', Rule::in(TemplateKey::values())],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:320'],

            // Hex only. The value is interpolated into a style attribute, so anything else would be
            // CSS injection wearing a colour's name.
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            'social' => ['sometimes', 'nullable', 'array'],
        ];

        foreach (self::SOCIAL_NETWORKS as $network) {
            $rules['social.'.$network] = ['sometimes', 'nullable', 'url', 'max:2048'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Use a six-digit hex colour, for example #0F766E.',
        ];
    }

    /**
     * Only the keys that were actually sent, so a partial save cannot blank a field it never
     * mentioned.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $attributes = $this->safe()->only([
            'template_key',
            'seo_title',
            'seo_description',
            'primary_color',
        ]);

        if ($this->has('social')) {
            // Unknown networks are dropped rather than rejected: the validator ignores keys it has
            // no rule for, and this is what stops them being stored.
            $social = (array) ($this->validated('social') ?? []);

            $attributes['social'] = array_intersect_key(
                $social,
                array_flip(self::SOCIAL_NETWORKS),
            );
        }

        return $attributes;
    }
}
