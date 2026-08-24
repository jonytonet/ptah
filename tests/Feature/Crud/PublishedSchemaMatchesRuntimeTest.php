<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Services\Validation\JsonSchemaBuilder;
use Ptah\Support\ModelKey;
use Ptah\Support\StyleRule;
use Ptah\Tests\TestCase;

class SchemaGuardStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * The guard that was missing, and that would have caught the contradiction the
 * day it appeared.
 *
 * JsonSchemaBuilder publishes the schema an integrator (or an agent) reads to
 * learn "what shape is a CRUD config". It had drifted so far from the runtime
 * that it described a section the renderer never reads (`styles`, while
 * getRowStyle reads `contitionStyles`), required only the legacy item shape
 * (rejecting exactly what `--style=` produces), and carried
 * `additionalProperties: false` over 7 declared keys while real configurations
 * carry 24+. Nothing failed, because nothing compared the published schema to
 * the configs the package itself produces.
 *
 * These tests do that comparison. There is no JSON Schema library in this
 * package's dependencies, so the checks are structural and deliberately narrow:
 * they assert the properties an integrator would actually rely on, not full
 * draft-07 semantics.
 */
class PublishedSchemaMatchesRuntimeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return (new JsonSchemaBuilder)->buildCrudConfigSchema();
    }

    #[Test]
    public function the_schema_names_the_style_key_the_renderer_actually_reads(): void
    {
        $properties = $this->schema()['properties'];

        // HasCrudRenderers::getRowStyle() reads 'contitionStyles' (the typo is
        // the ratified contract). A schema advertising 'styles' sends every
        // reader to a key whose rules silently never apply.
        $this->assertArrayHasKey(
            'contitionStyles',
            $properties,
            "O schema publicado deve declarar 'contitionStyles' — a chave que o runtime lê."
        );

        $this->assertArrayNotHasKey(
            'styles',
            $properties,
            "O schema nao deve anunciar 'styles': nada a le no render, e regras gravadas ali nunca se aplicam."
        );
    }

    #[Test]
    public function the_schema_root_is_open_because_real_configs_carry_far_more_keys(): void
    {
        $schema = $this->schema();

        // With additionalProperties:false over a partial key list, this schema
        // rejected every working config in existence (cacheStrategy,
        // configLinkLinha, crud, customFilters, uiPreferences, notifications…).
        $this->assertNotFalse(
            $schema['additionalProperties'] ?? true,
            'O root do schema nao pode ser fechado: ele declara um subconjunto das chaves reais.'
        );
    }

    #[Test]
    public function the_documented_style_conditions_cannot_drift_from_the_normaliser(): void
    {
        $canonical = $this->schema()['properties']['contitionStyles']['items']['anyOf'][0];

        $this->assertSame(
            StyleRule::CONDITIONS,
            $canonical['properties']['condition']['enum'],
            'O enum de condicoes do schema deve vir de StyleRule::CONDITIONS, senao documenta operador que o match() nao avalia.'
        );
    }

    #[Test]
    public function a_config_produced_by_the_cli_satisfies_the_published_schema(): void
    {
        // The end-to-end direction that matters: the package's own CLI writes a
        // config, and the package's own published schema must accept it.
        $this->artisan('ptah:config', [
            'model' => SchemaGuardStub::class,
            '--column' => ['name:text:label=Nome'],
            '--style' => ['status:==:cancelled:background:#FEE2E2;'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = CrudConfig::where('model', ModelKey::canonical(SchemaGuardStub::class))->first()?->config;

        $this->assertIsArray($config);
        $this->assertNotEmpty($config['contitionStyles'] ?? [], 'O CLI deve gravar a regra em contitionStyles.');

        $schema = $this->schema();

        // 1. No top-level key is rejected.
        foreach (array_keys($config) as $key) {
            $this->assertTrue(
                array_key_exists($key, $schema['properties']) || ($schema['additionalProperties'] ?? true) !== false,
                "O schema publicado rejeitaria a chave '{$key}', que o proprio ptah:config grava."
            );
        }

        // 2. Every style rule the CLI wrote satisfies one of the documented shapes.
        foreach ($config['contitionStyles'] as $index => $rule) {
            $this->assertTrue(
                $this->matchesAnyBranch($rule, $schema['properties']['contitionStyles']['items']['anyOf']),
                "A regra de estilo #{$index} gravada pelo CLI nao satisfaz nenhum dos itens documentados: ".json_encode($rule)
            );
        }
    }

    #[Test]
    public function a_legacy_shaped_rule_is_still_accepted_by_the_schema(): void
    {
        // StyleRule::normalize() still renders these, so a schema that rejected
        // them would report a problem that does not exist.
        $legacy = [
            'colsNomeFisico' => 'status',
            'colsOperator' => 'eq',
            'colsValue' => 'cancelled',
            'colsCss' => 'bg-red-50',
        ];

        $this->assertNotNull(StyleRule::normalize($legacy), 'Pre-condicao: o normalizador ainda aceita a forma legada.');

        $this->assertTrue(
            $this->matchesAnyBranch($legacy, $this->schema()['properties']['contitionStyles']['items']['anyOf']),
            'A forma legada renderiza mas nao e documentada — o schema estaria reprovando config valida.'
        );
    }

    #[Test]
    public function a_new_config_is_not_seeded_with_the_unread_styles_key(): void
    {
        $this->artisan('ptah:config', [
            'model' => SchemaGuardStub::class,
            '--column' => ['name:text:label=Nome'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = CrudConfig::where('model', ModelKey::canonical(SchemaGuardStub::class))->first()?->config;

        $this->assertIsArray($config);
        $this->assertArrayNotHasKey(
            'styles',
            $config,
            "Config nova nao deve nascer com 'styles': nada a le, e quem abrir o JSON escreve regras mortas nela."
        );
    }

    /**
     * Minimal structural check: does $rule satisfy the `required` list of at
     * least one anyOf branch, and its declared enums where present? Not a
     * draft-07 implementation — just enough to catch a schema that contradicts
     * the configs this package writes.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<int, array<string, mixed>>  $branches
     */
    private function matchesAnyBranch(array $rule, array $branches): bool
    {
        foreach ($branches as $branch) {
            $satisfied = true;

            foreach ($branch['required'] ?? [] as $required) {
                if (! array_key_exists($required, $rule)) {
                    $satisfied = false;
                    break;
                }
            }

            if (! $satisfied) {
                continue;
            }

            foreach ($branch['properties'] ?? [] as $name => $spec) {
                if (isset($spec['enum']) && array_key_exists($name, $rule) && ! in_array($rule[$name], $spec['enum'], true)) {
                    $satisfied = false;
                    break;
                }
            }

            if ($satisfied) {
                return true;
            }
        }

        return false;
    }
}
