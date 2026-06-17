<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Seo;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Full fallback chain for Seo::title():
 *   override -> listing content type / taxonomy label / seo field / record field
 *   -> configured default -> sitename. Also covers the title postfix and cleanUp().
 */
class SeoTitleTest extends SeoTestCase
{
    public function testOverrideTitleWins(): void
    {
        $seo = $this->makeSeo('homepage', [
            'override_default' => ['homepage' => ['title' => 'Override']],
        ]);

        self::assertSame('Override', $seo->title());
    }

    public function testListingUsesContentTypeName(): void
    {
        $seo = $this->makeSeo('listing', contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Blog', $seo->title());
    }

    public function testListingWithUnknownContentTypeFallsBackToSitename(): void
    {
        $seo = $this->makeSeo('listing', contentTypeSlug: 'ghost', contentType: null);

        self::assertSame('My Site', $seo->title());
    }

    public function testTaxonomyUsesTranslatedOverviewLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Overview for news');

        $seo = $this->makeSeo('taxonomy', translator: $translator);

        self::assertSame('Overview for news', $seo->title());
    }

    public function testRecordUsesSeoFieldTitle(): void
    {
        $record = $this->recordWithSeoData(['title' => 'SEO Title'], ['title' => 'Field Title']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('SEO Title', $seo->title());
    }

    public function testRecordFallsBackToContentTitleField(): void
    {
        // No seo field -> use the configured title field of the record.
        $record = $this->record(['title' => 'Field Title']);

        $seo = $this->makeSeo('record', record: $record);

        self::assertSame('Field Title', $seo->title());
    }

    public function testFallsBackToConfiguredDefaultTitle(): void
    {
        $seo = $this->makeSeo('record', [
            'default' => ['title' => 'Default Title', 'description' => '', 'keywords' => ''],
        ]);

        self::assertSame('Default Title', $seo->title());
    }

    public function testFallsBackToSitenameWhenNoDefaultTitle(): void
    {
        $seo = $this->makeSeo('record', siteName: 'Acme Inc');

        self::assertSame('Acme Inc', $seo->title());
    }

    public function testPostfixUsesConfiguredSeparatorAndText(): void
    {
        $seo = $this->makeSeo('listing', [
            'title_postfix' => 'Brand',
            'title_separator' => '-',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'));

        self::assertSame('Blog - Brand', $seo->title());
    }

    public function testPostfixDefaultsToSitenameWhenEmpty(): void
    {
        $seo = $this->makeSeo('listing', [
            'title_postfix' => '',
            'title_separator' => '',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), siteName: 'Acme');

        // empty separator -> '|', empty postfix -> sitename
        self::assertSame('Blog | Acme', $seo->title());
    }

    public function testPostfixOmitsSeparatorWhenResolvedValueIsEmpty(): void
    {
        // No configured postfix and no sitename to fall back to: the resolved postfix
        // is empty, so the separator must be omitted too (no dangling " | ").
        $seo = $this->makeSeo('listing', [
            'title_postfix' => '',
            'title_separator' => '|',
        ], contentTypeSlug: 'blog', contentType: $this->contentType('Blog'), siteName: null);

        self::assertSame('Blog', $seo->title());
    }

    public function testCleanUpStripsTagsAndCollapsesWhitespace(): void
    {
        $seo = $this->makeSeo('homepage', [
            'override_default' => ['homepage' => ['title' => "<b>Bold</b>  and\r\n spaced"]],
        ]);

        self::assertSame('Bold and spaced', $seo->title());
    }
}
