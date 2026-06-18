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

    private function makeField(string $value): SeoField
    {
        $field = new SeoField();
        $field->setDefinition('seo', new FieldType(['type' => 'seo']));
        $field->setName('seo');
        $field->setValue($value);

        return $field;
    }
}
