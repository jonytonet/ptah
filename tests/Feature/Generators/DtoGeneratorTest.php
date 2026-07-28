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

    /**
     * fromRequest() needs FormRequest::validated(), which does not exist on
     * the base Illuminate\Http\Request — regression for a bug found while
     * hardening the stubs for static analysis.
     */
    #[Test]
    public function it_types_form_request_for_the_validated_call(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('use Illuminate\Foundation\Http\FormRequest;', $content);
        $this->assertStringContainsString('@var FormRequest $request', $content);
        $this->assertStringContainsString('$request->validated()', $content);

        // The native parameter type must stay Request: narrowing it to
        // FormRequest would violate BaseDTO::fromRequest(Request $request)'s
        // signature and fatal at class-declaration time (LSP contravariance).
        $this->assertStringContainsString('public static function fromRequest(Request $request): static', $content);
    }

    /**
     * Loads the generated DTO together with the real Ptah\Base\BaseDTO in a
     * fresh PHP process: proves the class declares without a "Declaration ...
     * must be compatible with BaseDTO::fromRequest(Request $request)" fatal
     * error, which a plain `php -l` on the isolated file would NOT catch
     * (BaseDTO is not defined in that file, so the lint never resolves the
     * inheritance chain).
     */
    #[Test]
    public function generated_dto_declares_without_a_fatal_lsp_error_against_base_dto(): void
    {
        $result = (new DtoGenerator($this->files))->generate($this->context());
        $autoload = dirname(__DIR__, 3).'/vendor/autoload.php';

        // Written to a file (instead of `php -r <script>`) to avoid shell
        // quoting pitfalls with nested double quotes on Windows.
        $harness = $this->tmpPath.'/dto-lsp-harness.php';
        $this->files->put($harness, sprintf(
            "<?php\nrequire %s;\nrequire %s;\nclass_exists(%s);\necho 'OK';\n",
            var_export($autoload, true),
            var_export($result->path, true),
            var_export('App\\DTOs\\WidgetDTO', true),
        ));

        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($harness).' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $this->assertStringContainsString('OK', implode("\n", $output));
    }
}
