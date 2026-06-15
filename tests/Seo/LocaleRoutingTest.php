<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers locale-aware routing: localized listing routes (`listing_locale`) must
 * behave like their non-localized counterpart (`listing`), localized non-listing
 * routes (e.g. `record_locale`) must fall through to the same record handling as
 * `record`, and per-locale override values must merge over the base override.
 */
class LocaleRoutingTest extends SeoTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function listingRoutes(): array
    {
        return [
            'non-localized listing' => ['listing'],
            'localized listing' => ['listing_locale'],
        ];
    }

    #[DataProvider('listingRoutes')]
    public function testListingRoutesResolveContentTypeName(string $route): void
    {
        $seo = $this->makeSeo($route, contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Blog', $seo->title());
    }

    #[DataProvider('listingRoutes')]
    public function testListingRoutesUseContentTypeSlugOverride(string $route): void
    {
        $seo = $this->makeSeo($route, [
            'override_default' => ['blog' => ['title' => 'Our Blog', 'description' => 'News']],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Our Blog', $seo->title());
        self::assertSame('News', $seo->description());
    }

    #[DataProvider('listingRoutes')]
    public function testListingRoutesFallBackToSitenameWhenContentTypeMissing(string $route): void
    {
        $seo = $this->makeSeo($route, contentTypeSlug: 'ghost', contentType: null);

        self::assertSame('My Site', $seo->title());
    }

    public function testRouteNameOverrideTakesPrecedenceOverContentTypeSlug(): void
    {
        $seo = $this->makeSeo('listing', [
            'override_default' => [
                'listing' => ['title' => 'By route'],
                'blog' => ['title' => 'By slug'],
            ],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('By route', $seo->title());
    }

    public function testLocalizedListingHonorsItsOwnRouteNameOverride(): void
    {
        // Route-name overrides are keyed by the exact route, so `listing_locale`
        // must be addressable independently from `listing`.
        $seo = $this->makeSeo('listing_locale', [
            'override_default' => ['listing_locale' => ['title' => 'Localized listing']],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Localized listing', $seo->title());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function recordRoutes(): array
    {
        return [
            'non-localized record' => ['record'],
            'localized record' => ['record_locale'],
        ];
    }

    #[DataProvider('recordRoutes')]
    public function testRecordRoutesResolveTitleFromRecordField(string $route): void
    {
        $seo = $this->makeSeo($route, record: $this->record(['title' => 'My Article']));

        self::assertSame('My Article', $seo->title());
    }

    #[DataProvider('recordRoutes')]
    public function testRecordRoutesFallBackToSitenameWithoutRecord(string $route): void
    {
        $seo = $this->makeSeo($route);

        self::assertSame('My Site', $seo->title());
    }

    // --- per-locale override values (bde30c1) ------------------------------

    public function testLocaleOverrideWinsForCurrentLocale(): void
    {
        $seo = $this->makeSeo('listing', [
            'override_default' => ['blog' => [
                'title' => 'Base Title',
                'locales' => ['en' => ['title' => 'English Title']],
            ]],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), locale: 'en');

        self::assertSame('English Title', $seo->title());
    }

    public function testLocaleOverrideFallsBackToBaseForUnknownLocale(): void
    {
        $seo = $this->makeSeo('listing', [
            'override_default' => ['blog' => [
                'title' => 'Base Title',
                'locales' => ['en' => ['title' => 'English Title']],
            ]],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), locale: 'fr');

        self::assertSame('Base Title', $seo->title());
    }

    public function testLocaleOverrideMergesPartiallyOverBase(): void
    {
        // Locale only overrides the title; description must still come from the base.
        $seo = $this->makeSeo('listing', [
            'override_default' => ['blog' => [
                'title' => 'Base Title',
                'description' => 'Base description',
                'locales' => ['en' => ['title' => 'English Title']],
            ]],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), locale: 'en');

        self::assertSame('English Title', $seo->title());
        self::assertSame('Base description', $seo->description());
    }

    public function testNonListingRouteSupportsLocaleOverride(): void
    {
        // Per-locale overrides work for any resolved override, including non-listing routes.
        $seo = $this->makeSeo('record', [
            'override_default' => ['record' => [
                'title' => 'Base Title',
                'locales' => ['de' => ['title' => 'Deutscher Titel']],
            ]],
        ], locale: 'de');

        self::assertSame('Deutscher Titel', $seo->title());
    }

    public function testLocalizedListingSlugOverrideMergesLocale(): void
    {
        // On `listing_locale` the slug-keyed override must still merge per-locale
        // values for the current locale (the localized counterpart of the `listing` case).
        $seo = $this->makeSeo('listing_locale', [
            'override_default' => ['blog' => [
                'title' => 'Base Title',
                'description' => 'Base description',
                'locales' => ['en' => ['title' => 'English Title']],
            ]],
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), locale: 'en');

        self::assertSame('English Title', $seo->title());
        self::assertSame('Base description', $seo->description());
    }

    // --- `<route>_locale` falls back to its base route override key ---------

    public function testLocalizedRouteFallsBackToBaseRouteOverride(): void
    {
        $seo = $this->makeSeo('homepage_locale', [
            'override_default' => ['homepage' => ['title' => 'Home Override']],
        ]);

        self::assertSame('Home Override', $seo->title());
    }

    public function testLocalizedRouteBaseFallbackSupportsLocaleMerge(): void
    {
        $seo = $this->makeSeo('homepage_locale', [
            'override_default' => ['homepage' => [
                'title' => 'Base',
                'locales' => ['de' => ['title' => 'Startseite']],
            ]],
        ], locale: 'de');

        self::assertSame('Startseite', $seo->title());
    }

    public function testExactLocalizedRouteOverrideWinsOverBaseRoute(): void
    {
        $seo = $this->makeSeo('homepage_locale', [
            'override_default' => [
                'homepage_locale' => ['title' => 'Exact'],
                'homepage' => ['title' => 'Base'],
            ],
        ]);

        self::assertSame('Exact', $seo->title());
    }

    public function testLocalizedRouteWithoutBaseOverrideFallsBackToSitename(): void
    {
        $seo = $this->makeSeo('homepage_locale', [
            'override_default' => ['contact' => ['title' => 'Contact']],
        ], siteName: 'My Site');

        self::assertSame('My Site', $seo->title());
    }
}
