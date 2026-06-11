<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ResourceGenerator;

/**
 * Covers the generated API Resource: every entity field plus audit and
 * timestamp columns must appear in toArray().
 */
class ResourceGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_maps_all_fields_and_audit_columns_in_to_array(): void
    {
        $result = (new ResourceGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('class WidgetResource', $content);

        foreach (['id', 'name', 'price', 'category_id', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at'] as $field) {
            $this->assertStringContainsString(
                "'{$field}' => \$this->{$field},",
                $content,
                "Resource toArray() must expose '{$field}'",
            );
        }
    }

    #[Test]
    public function it_omits_deleted_by_without_soft_deletes(): void
    {
        $result = (new ResourceGenerator($this->files))->generate($this->context(withSoftDeletes: false));

        $content = (string) file_get_contents($result->path);

        $this->assertStringNotContainsString("'deleted_by'", $content);
    }
}
