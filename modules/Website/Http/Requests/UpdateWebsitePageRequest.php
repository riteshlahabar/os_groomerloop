<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Website\Domain\PageKey;
use Symfony\Component\HttpFoundation\Response;

/**
 * One page's content, validated against the fields that page actually has (spec §14).
 *
 * The allowed fields come from {@see PageKey::contentFields()} and {@see PageKey::contentLists()},
 * so the editor, the validator and the templates all read one definition. Anything the page does not
 * declare is not validated and is therefore never stored.
 */
final class UpdateWebsitePageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->pageKey();

        $rules = [
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'content' => ['sometimes', 'array'],
        ];

        // Home has no off switch (a site needs an entry point), so the key is refused outright
        // rather than silently ignored — a UI that offers the toggle is the bug to surface.
        $rules['is_enabled'] = $page->isMandatory()
            ? ['prohibited']
            : ['sometimes', 'boolean'];

        foreach ($page->contentFields() as $field => $rule) {
            $rules['content.'.$field] = $rule;
        }

        foreach ($page->contentLists() as $field => $definition) {
            $rules['content.'.$field] = ['sometimes', 'array', 'max:'.$definition['max']];

            foreach ($definition['rules'] as $rowField => $rule) {
                $rules['content.'.$field.'.*.'.$rowField] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_enabled.prohibited' => 'The home page cannot be switched off — it is the site\'s address.',
        ];
    }

    /**
     * The page named in the URL. A slug that is not one of the fixed §14 pages is a 404, not a
     * validation error: the resource does not exist.
     */
    public function pageKey(): PageKey
    {
        $key = PageKey::tryFrom((string) $this->route('pageKey'));

        abort_if($key === null, Response::HTTP_NOT_FOUND);

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function pageAttributes(): array
    {
        $attributes = $this->safe()->only(['title', 'is_enabled', 'seo_title', 'seo_description']);

        if ($this->has('content')) {
            // Only validated content keys survive: `validated()` returns exactly what the rules
            // above described, so an extra field a client invented is dropped here rather than
            // persisted into the page's JSON and rendered later.
            $attributes['content'] = $this->validatedContent();
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedContent(): array
    {
        $content = (array) ($this->validated()['content'] ?? []);
        $page = $this->pageKey();

        $allowed = array_merge(
            array_keys($page->contentFields()),
            array_keys($page->contentLists()),
        );

        return array_intersect_key($content, array_flip($allowed));
    }
}
