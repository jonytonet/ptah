<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ResourceGenerator;
use Ptah\Support\EntityContext;

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

    /**
     * The generated @mixin tag points PHPStan/IDEs at the real Eloquent
     * model backing the resource, so `$this->{field}` (a magic __get on
     * JsonResource) resolves instead of raising a swarm of "access to
     * undefined property" errors.
     */
    #[Test]
    public function it_mixes_in_the_backing_model_fqn(): void
    {
        $result = (new ResourceGenerator($this->files))->generate($this->context());

        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('@mixin \App\Models\Widget', $content);
    }

    #[Test]
    public function it_mixes_in_the_backing_model_fqn_with_a_subfolder(): void
    {
        $context = new EntityContext(
            entity: 'Widget',
            entityLower: 'widget',
            entityPlural: 'widgets',
            entityPluralStudly: 'Widgets',
            table: 'widgets',
            rootNamespace: 'App\\',
            timestamp: date('Y_m_d_His'),
            withViews: true,
            withSoftDeletes: true,
            force: false,
            fields: $this->defaultFields(),
            subFolder: 'Catalog',
        );

        $result = (new ResourceGenerator($this->files))->generate($context);
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('@mixin \App\Models\Catalog\Widget', $content);
    }
}
