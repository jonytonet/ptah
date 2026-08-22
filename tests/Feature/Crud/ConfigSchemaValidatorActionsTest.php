<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Services\Validation\JsonSchemaBuilder;
use Ptah\Tests\TestCase;

/**
 * Regression coverage for a validator/runtime drift found by audit:
 * ConfigSchemaValidator (and JsonSchemaBuilder, its documentation-schema
 * twin) used to validate `actionType` against ['wire', 'route', 'url',
 * 'modal'] — a list the table renderer never executes. The renderer
 * (resources/views/livewire/base-crud/partials/_table.blade.php:212-239)
 * only understands 'link', 'livewire' and 'javascript' (default). Both
 * validators now read CrudConfigEnums::ACTION_TYPES, the single source of
 * truth, so they can never drift apart from each other or from the runtime
 * again.
 */
class ConfigSchemaValidatorActionsTest extends TestCase
{
    private function validator(): ConfigSchemaValidator
    {
        return new ConfigSchemaValidator;
    }

    #[Test]
    public function each_runtime_action_type_passes_validation(): void
    {
        foreach (['link', 'livewire', 'javascript'] as $type) {
            $this->validator()->validate([
                'actions' => [
                    ['colsNomeLogico' => 'Approve', 'actionType' => $type],
                ],
            ], 'Widget');
        }

        $this->addToAssertionCount(3);
    }

    #[Test]
    public function a_legacy_action_type_that_the_runtime_never_executed_now_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'actions' => [
                ['colsNomeLogico' => 'Approve', 'actionType' => 'wire'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function the_error_message_lists_the_real_runtime_action_types(): void
    {
        try {
            $this->validator()->validate([
                'actions' => [
                    ['colsNomeLogico' => 'Approve', 'actionType' => 'modal'],
                ],
            ], 'Widget');
            $this->fail('Expected a ConfigValidationException');
        } catch (ConfigValidationException $e) {
            $this->assertStringContainsString('link', $e->getMessage());
            $this->assertStringContainsString('livewire', $e->getMessage());
            $this->assertStringContainsString('javascript', $e->getMessage());
            $this->assertStringNotContainsString('wire, route, url, modal', $e->getMessage());
        }
    }

    /**
     * Guard: fixes CrudConfigEnums::ACTION_TYPES — the list both
     * ConfigSchemaValidator and JsonSchemaBuilder consume — to exactly the
     * types the table renderer's @if/@elseif chain understands. If someone
     * adds a new actionType to the renderer (or removes one) without
     * touching this constant, this test fails instead of the two validators
     * silently drifting from the runtime again.
     */
    #[Test]
    public function action_types_enum_matches_exactly_what_the_table_renderer_executes(): void
    {
        $executedByRenderer = ['link', 'livewire', 'javascript'];

        sort($executedByRenderer);
        $enumTypes = CrudConfigEnums::ACTION_TYPES;
        sort($enumTypes);

        $this->assertSame($executedByRenderer, $enumTypes);
    }

    #[Test]
    public function json_schema_builder_documents_the_same_action_types_as_the_validator(): void
    {
        $schema = (new JsonSchemaBuilder)->buildCrudConfigSchema();

        $documented = $schema['properties']['actions']['items']['properties']['colsTipo']['enum'];

        $this->assertSame(CrudConfigEnums::ACTION_TYPES, $documented);
    }
}
