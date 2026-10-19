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

    /** 1.46.5 (Engepecas): `yelll_contact_id` e o id de um CRM externo, nao uma FK. */
    #[Test]
    public function an_external_pointer_gets_a_comment_not_a_belongs_to(): void
    {
        config(['ptah.forge.external_pointer_prefixes' => ['yelll_']]);
        $fields = [...$this->defaultFields(), $this->field('yelll_contact_id', 'unsignedBigInteger')];

        $content = $this->generate(fields: $fields);

        $this->assertStringNotContainsString('function yelllContact', $content);
        $this->assertStringNotContainsString('YelllContact', $content);
        $this->assertStringContainsString('// yelll_contact_id: ponteiro externo', $content);
        $this->assertStringContainsString("belongsTo(Category::class, 'category_id')", $content, 'FK comum segue gerando.');

        $ext = $this->field('yelll_contact_id', 'foreignId');
        $this->assertStringNotContainsString('constrained', $ext->migrationLine());
        $this->assertStringContainsString("unsignedBigInteger('yelll_contact_id')->index()", $ext->migrationLine());
        $this->assertStringNotContainsString('factory', $ext->fakerExpression());
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

        // 1.44.0: atributos PHP, nao docblock — o swagger-php 6 so le atributos.
        $this->assertStringContainsString('#[OA\Schema(', $content);
        $this->assertStringContainsString('use OpenApi\Attributes as OA;', $content);
        $this->assertStringContainsString("schema: 'Widget'", $content);
        // decimal → number/float, FK → integer
        $this->assertStringContainsString("property: 'price', type: 'number', format: 'float'", $content);
        $this->assertStringContainsString("property: 'category_id', type: 'integer'", $content);
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
    public function adding_the_api_injects_the_schema_attribute_into_an_existing_model_once(): void
    {
        $web = (new ModelGenerator($this->files))->generate($this->context(withApi: false, withViews: true));
        $this->assertTrue($web->isDone(), $web->message ?? '');

        $inject = (new ModelGenerator($this->files))->generate($this->context(withApi: true, withViews: true));
        $this->assertTrue($inject->isDone(), $inject->message ?? '');
        $content = (string) file_get_contents($inject->path);

        $this->assertSame(1, substr_count($content, 'use OpenApi\Attributes as OA;'));
        $this->assertMatchesRegularExpression('/#\[OA\\\\Schema\([\s\S]*?\)\]\s*class Widget /', $content);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($inject->path), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));

        $again = (new ModelGenerator($this->files))->generate($this->context(withApi: true, withViews: true));
        $this->assertFalse($again->isDone(), 'O schema foi injetado duas vezes.');
    }

    #[Test]
    public function generated_model_is_valid_php(): void
    {
        $result = (new ModelGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
