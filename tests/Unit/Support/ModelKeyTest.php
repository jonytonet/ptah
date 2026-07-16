<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

/**
 * ModelKey::canonical() must collapse every way of naming a model down to the
 * single key BaseCrud reads (sub-folder form, no App\Models prefix) — the fix
 * for the FQCN-vs-slash orphan footgun.
 */
class ModelKeyTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cases(): array
    {
        return [
            'fqcn nested' => ['App\\Models\\Catalog\\Product', 'Catalog/Product'],
            'fqcn root' => ['App\\Models\\Product', 'Product'],
            'fqcn leading slash' => ['\\App\\Models\\Product', 'Product'],
            'slash nested' => ['Catalog/Product', 'Catalog/Product'],
            'slash root' => ['Product', 'Product'],
            'app-models slash' => ['App/Models/Catalog/Product', 'Catalog/Product'],
            'whitespace' => ['  App\\Models\\Product  ', 'Product'],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function canonicalises_every_form_to_the_runtime_key(string $input, string $expected): void
    {
        $this->assertSame($expected, ModelKey::canonical($input));
    }

    #[Test]
    public function is_canonical_flags_the_fqcn_orphan_form(): void
    {
        $this->assertTrue(ModelKey::isCanonical('Catalog/Product'));
        $this->assertFalse(ModelKey::isCanonical('App\\Models\\Catalog\\Product'));
    }

    #[Test]
    public function canonical_is_idempotent(): void
    {
        $once = ModelKey::canonical('App\\Models\\Catalog\\Product');
        $this->assertSame($once, ModelKey::canonical($once));
    }
}
