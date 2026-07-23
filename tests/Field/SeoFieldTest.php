<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Tests\Field;

use Appolo\BoltSeo\Field\SeoField;
use Bolt\Configuration\Content\FieldType;
use Bolt\Entity\Field;
use Bolt\Entity\Field\Excerptable;
use Bolt\Entity\Field\RawPersistable;
use Bolt\Entity\FieldInterface;
use PHPUnit\Framework\TestCase;
use Twig\Markup;

/**
 * Smoke tests for the custom `seo` field type: it must declare the expected type
 * and contracts, and render its stored JSON as Twig Markup (unescaped) rather
 * than a plain string.
 */
class SeoFieldTest extends TestCase
{
    public function testTypeAndContracts(): void
    {
        $field = new SeoField();

        self::assertSame('seo', SeoField::TYPE);
        self::assertInstanceOf(Field::class, $field);
        self::assertInstanceOf(FieldInterface::class, $field);
        self::assertInstanceOf(Excerptable::class, $field);
        self::assertInstanceOf(RawPersistable::class, $field);
    }

    public function testGetTwigValueReturnsMarkupWithStoredValue(): void
    {
        $field = $this->makeField('{"title":"Hello"}');

        $value = $field->getTwigValue();

        self::assertInstanceOf(Markup::class, $value);
        self::assertSame('{"title":"Hello"}', (string) $value);
    }

    /**
     * The `seo` field is `RawPersistable` (Bolt skips its sanitiser on save) and
     * `getTwigValue()` returns the value as `Markup`, i.e. Twig does not escape
     * it. `{{ record.seo }}` therefore emits the stored JSON verbatim as HTML.
     * If an editor stores markup in a value (client-side stripping in seo.js is
     * trivially bypassed by posting raw), that markup would execute — stored XSS.
     * The rendered value must carry no HTML tags while staying valid JSON.
     */
    public function testGetTwigValueStripsHtmlFromStoredValuesToPreventStoredXss(): void
    {
        $field = $this->makeField(
            '{"title":"<script>alert(1)</script>Hi","description":"<img src=x onerror=alert(1)>desc"}'
        );

        $rendered = (string) $field->getTwigValue();

        self::assertStringNotContainsStringIgnoringCase('<script', $rendered);
        self::assertStringNotContainsStringIgnoringCase('<img', $rendered);
        self::assertStringNotContainsStringIgnoringCase('onerror', $rendered);

        $decoded = json_decode($rendered, true);
        self::assertIsArray($decoded);
        self::assertSame('alert(1)Hi', $decoded['title']);
        self::assertSame('desc', $decoded['description']);
    }

    public function testGetTwigValueNeutralisesNonJsonValues(): void
    {
        // Corrupt / legacy raw values that are not JSON must still never be
        // emitted as live markup.
        $field = $this->makeField('<script>alert(1)</script>');

        self::assertStringNotContainsStringIgnoringCase('<script', (string) $field->getTwigValue());
    }

    private function makeField(string $value): SeoField
    {
        $field = new SeoField();
        $field->setDefinition('seo', new FieldType(['type' => 'seo']));
        $field->setName('seo');
        $field->setValue($value);

        return $field;
    }
}
