<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ModelGenerator;

/**
 * Covers the generated Eloquent model: fillable, casts, soft-deletes wiring,
 * FK relationships and the Swagger schema injection in API mode.
 */
class ModelGeneratorTest extends GeneratorTestCase
{
    private function generate(...$args): string
    {
        $result = (new ModelGenerator($this->files))->generate($this->context(...$args));

        $this->assertTrue($result->isDone(), $result->message ?? '');

        return (string) file_get_contents($result->path);
    }

    #[Test]
    public function it_generates_fillable_with_fields_and_audit_columns(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString("'name'", $content);
        $this->assertStringContainsString("'price'", $content);
        $this->assertStringContainsString("'category_id'", $content);
        $this->assertStringContainsString("'created_by'", $content);
        $this->assertStringContainsString("'updated_by'", $content);
        $this->assertStringContainsString("'deleted_by'", $content);
    }

    #[Test]
    public function it_generates_casts_for_each_field_type(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString("'price' => 'decimal:2',", $content);
        $this->assertStringContainsString("'category_id' => 'integer',", $content);
        $this->assertStringContainsString("'created_by' => 'integer',", $content);
    }

    #[Test]
    public function it_wires_soft_deletes_when_enabled(): void
    {
        $content = $this->generate(withSoftDeletes: true);

        $this->assertStringContainsString('use Illuminate\Database\Eloquent\SoftDeletes;', $content);
        $this->assertStringContainsString('use SoftDeletes;', $content);
    }

    #[Test]
    public function it_omits_soft_deletes_when_disabled(): void
    {
        $content = $this->generate(withSoftDeletes: false);

        $this->assertStringNotContainsString('SoftDeletes', $content);
        $this->assertStringNotContainsString("'deleted_by'", $content);
    }

    #[Test]
    public function it_generates_belongs_to_for_fk_fields_with_todo_import(): void
    {
        $content = $this->generate();

        // belongsTo method for category_id
        $this->assertStringContainsString('public function category()', $content);
        $this->assertStringContainsString("belongsTo(Category::class, 'category_id')", $content);

        // Import is a TODO (developer must confirm the real namespace)
        $this->assertStringContainsString('// TODO: use App\Models\Category;', $content);
    }

    #[Test]
    public function it_always_uses_has_audit_fields_trait(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('use Ptah\Traits\HasAuditFields;', $content);
        $this->assertStringContainsString('HasFactory, HasAuditFields', $content);
    }

    #[Test]
    public function it_injects_swagger_schema_in_api_mode(): void
    {
        $content = $this->generate(withApi: true, withViews: false);

        $this->assertStringContainsString('@OA\Schema(', $content);
        $this->assertStringContainsString('schema="Widget"', $content);
        // decimal → number/float, FK → integer
        $this->assertStringContainsString('property="price", type="number", format="float"', $content);
        $this->assertStringContainsString('property="category_id", type="integer"', $content);
    }

    #[Test]
    public function it_skips_existing_file_without_force(): void
    {
        $generator = new ModelGenerator($this->files);

        $first = $generator->generate($this->context());
        $this->assertTrue($first->isDone());

        $second = $generator->generate($this->context());
        $this->assertTrue($second->isSkipped());
    }

    #[Test]
    public function generated_model_is_valid_php(): void
    {
        $result = (new ModelGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
