<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\DtoGenerator;

/**
 * Covers the generated DTO: typed readonly properties, nullable handling
 * and the fromArray mapping.
 */
class DtoGeneratorTest extends GeneratorTestCase
{
    private function generate(): string
    {
        $result = (new DtoGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');

        return (string) file_get_contents($result->path);
    }

    #[Test]
    public function it_generates_typed_readonly_properties(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('class WidgetDTO extends BaseDTO', $content);
        $this->assertStringContainsString('public readonly string $name,', $content);
        // nullable decimal → ?float with a null default
        $this->assertStringContainsString('public readonly ?float $price = null,', $content);
        $this->assertStringContainsString('public readonly int $category_id,', $content);
    }

    #[Test]
    public function it_generates_from_array_with_null_coalescing_for_nullables(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString("name: \$data['name'],", $content);
        $this->assertStringContainsString("price: \$data['price'] ?? null,", $content);
        $this->assertStringContainsString("category_id: \$data['category_id'],", $content);
    }

    #[Test]
    public function required_properties_come_before_optional_ones(): void
    {
        // PHP 8 deprecates optional parameters declared before required ones.
        // Fields arrive as [name (required), price (nullable), category_id (required)]
        // and the generator must reorder them: required first.
        $content = $this->generate();

        $namePos = strpos($content, '$name');
        $categoryPos = strpos($content, '$category_id');
        $pricePos = strpos($content, '$price');

        $this->assertLessThan($pricePos, $namePos, 'required $name must precede optional $price');
        $this->assertLessThan($pricePos, $categoryPos, 'required $category_id must precede optional $price');
    }

    #[Test]
    public function generated_dto_is_valid_php(): void
    {
        $result = (new DtoGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
