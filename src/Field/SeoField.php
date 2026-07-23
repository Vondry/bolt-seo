<?php

declare(strict_types=1);

namespace Appolo\BoltSeo\Field;

use Bolt\Entity\Field;
use Bolt\Entity\Field\Excerptable;
use Bolt\Entity\Field\RawPersistable;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;
use Twig\Markup;

#[ORM\Entity]
class SeoField extends Field implements Excerptable, FieldInterface, RawPersistable
{
    public const TYPE = 'seo';

    /**
     * Render the field as HTML markup.
     *
     * This field is RawPersistable, so Bolt skips its sanitiser on save, and the
     * value is emitted unescaped (as Markup) wherever it is rendered, e.g.
     * `{{ record.seo }}`. Strip HTML from every stored string so editor-supplied
     * markup can never execute (stored XSS) — the browser-side stripping in
     * seo.js is only advisory and is bypassed by posting the field raw.
     *
     * The payload is kept as valid JSON so the front-end SEO service, which
     * decodes this same value, keeps working.
     */
    public function getTwigValue()
    {
        $value = (string) $this->getParsedValue();
        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            array_walk_recursive($decoded, static function (&$item): void {
                if (is_string($item)) {
                    $item = strip_tags($item);
                }
            });
            $value = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            // Not an object (corrupt or legacy value): never emit it as live markup.
            $value = strip_tags($value);
        }

        return new Markup($value, 'UTF-8');
    }
}
