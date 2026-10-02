<?php

namespace Modules\Website\Domain;

/**
 * The pages a tenant site can have (spec §14).
 *
 * A fixed set, not user-created pages. §37 rules this product out of being a website builder or a
 * CMS: the job is to give a grooming business a correct, bookable presence with no decisions to
 * make, so the pages are the ones every salon needs and each maps onto a page of the
 * owner-supplied template bundle.
 *
 * `Home` is always enabled — a site with no home page has no public entry point. Every other page
 * can be switched off, which hides it from the navigation and 404s its URL.
 */
enum PageKey: string
{
    case Home = 'home';
    case Services = 'services';
    case About = 'about';
    case Gallery = 'gallery';
    case Team = 'team';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Services => 'Services',
            self::About => 'About us',
            self::Gallery => 'Gallery',
            self::Team => 'Our team',
            self::Contact => 'Contact',
        };
    }

    /**
     * Home is the site's entry point and cannot be switched off.
     */
    public function isMandatory(): bool
    {
        return $this === self::Home;
    }

    /**
     * Where this page sits in the public navigation, and in the §14 editor's page list.
     */
    public function sort(): int
    {
        return match ($this) {
            self::Home => 0,
            self::Services => 1,
            self::About => 2,
            self::Gallery => 3,
            self::Team => 4,
            self::Contact => 5,
        };
    }

    /**
     * The content fields this page accepts, as validation rules fragments keyed by field.
     *
     * Held on the enum rather than in the request class so the editor screen, the renderer and
     * the validator cannot drift apart: adding a field is one edit here.
     *
     * @return array<string, string>
     */
    public function contentFields(): array
    {
        return match ($this) {
            self::Home => [
                'eyebrow' => 'nullable|string|max:120',
                'headline' => 'nullable|string|max:160',
                'subheadline' => 'nullable|string|max:300',
                'intro' => 'nullable|string|max:2000',
                'cta_label' => 'nullable|string|max:60',
            ],
            self::Services => [
                'headline' => 'nullable|string|max:160',
                'intro' => 'nullable|string|max:2000',
            ],
            self::About => [
                'headline' => 'nullable|string|max:160',
                'body' => 'nullable|string|max:5000',
                'image_url' => 'nullable|url|max:2048',
            ],
            self::Gallery => [
                'headline' => 'nullable|string|max:160',
                'intro' => 'nullable|string|max:2000',
            ],
            self::Team => [
                'headline' => 'nullable|string|max:160',
                'intro' => 'nullable|string|max:2000',
            ],
            self::Contact => [
                'headline' => 'nullable|string|max:160',
                'intro' => 'nullable|string|max:2000',
                'map_embed_url' => 'nullable|url|max:2048',
            ],
        };
    }

    /**
     * Repeatable blocks this page accepts: field => [max rows, row rules].
     *
     * Gallery images and home highlights are lists the owner edits; services and staff are NOT
     * here, because they are read live through Catalog's and Team's contracts and must never be
     * retyped into website content (they would then go stale the moment a price changes).
     *
     * @return array<string, array{max: int, rules: array<string, string>}>
     */
    public function contentLists(): array
    {
        return match ($this) {
            self::Home => [
                'highlights' => ['max' => 6, 'rules' => [
                    'title' => 'nullable|string|max:120',
                    'text' => 'nullable|string|max:400',
                ]],
                'testimonials' => ['max' => 6, 'rules' => [
                    'name' => 'nullable|string|max:120',
                    'quote' => 'nullable|string|max:600',
                ]],
            ],
            self::Gallery => [
                'images' => ['max' => 24, 'rules' => [
                    'url' => 'required|url|max:2048',
                    'caption' => 'nullable|string|max:160',
                ]],
            ],
            default => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $key): string => $key->value, self::cases());
    }
}
