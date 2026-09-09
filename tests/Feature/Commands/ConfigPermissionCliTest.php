<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class PermissionCliStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * `ptah:config --permission="showCreateButton=false"` reported success and
 * changed nothing.
 *
 * The command wrote the RAW string. Nine lines above, in the same method,
 * `--set` runs its value through `castValue()`, which turns `'false'` into
 * `false`; `--permission` skipped it. So the config stored the string
 * `"false"`, and every consumer treats it as a boolean:
 *
 *   CrudConfig.php:431   (bool) ($perms['showCreateButton'] ?? true)
 *   BaseCrud.php:493     ($p['showCreateButton'] ?? true) && …
 *
 * `(bool) "false"` is `true` in PHP, and a non-empty string is truthy in an
 * `&&`. The button stayed on screen, with "✓ Configuration saved" in the
 * terminal and nothing in any log.
 *
 * ── Why it went unnoticed ────────────────────────────────────────────────
 *
 * The same flag writes nine keys. Five of them — `create`, `edit`, `delete`,
 * `export`, `restore` — hold a GATE NAME, a string, and worked perfectly
 * through the same broken line. Only the four booleans were unreachable. A flag
 * that works in five of nine cases looks like a flag that works, which is
 * exactly how `--filter` survived several releases documented as functional
 * while every call failed (see `ConfigFilterCliTest`, and §5 of
 * KnownLimitations).
 *
 * So these tests pin both halves: the four booleans arrive typed, and the five
 * strings are still untouched. `castValue()` returns anything that is not
 * true/false/null/numeric unchanged, which is what makes the one-line fix safe
 * — but "should be safe" is the claim, and the claim is what a test is for.
 */
class ConfigPermissionCliTest extends TestCase
{
    /**
     * A base config with one column, because the toolbar (and therefore the
     * Novo button) does not render for a model with no configured columns —
     * so without this the "button disappeared" assertion would pass on a screen
     * that never had a button. It is also the real sequence: you configure a
     * CRUD, then you turn a button off.
     */
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::updateOrCreate(
            ['model' => ModelKey::canonical(PermissionCliStub::class), 'route' => ''],
            ['config' => [
                'crud' => PermissionCliStub::class,
                'cols' => [[
                    'colsNomeFisico' => 'name',
                    'colsNomeLogico' => 'Nome',
                    'colsTipo' => 'text',
                    'colsVisibleList' => true,
                    'colsGravar' => true,
                ]],
                'permissions' => [],
            ]]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storedConfig(): array
    {
        return CrudConfig::where('model', ModelKey::canonical(PermissionCliStub::class))->first()?->config ?? [];
    }

    /**
     * Nao chamar isto de `run()`: o TestCase do PHPUnit tem um `run()` final, e
     * sobrescrever da fatal antes de qualquer teste rodar. Segunda vez que caio
     * nisso nesta base.
     *
     * @param  list<string>  $permissions
     */
    private function configure(array $permissions): void
    {
        $this->artisan('ptah:config', [
            'model' => PermissionCliStub::class,
            '--permission' => $permissions,
            '--non-interactive' => true,
        ])->assertExitCode(0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function booleanFlagProvider(): array
    {
        return [
            'showCreateButton' => ['showCreateButton'],
            'showEditButton' => ['showEditButton'],
            'showDeleteButton' => ['showDeleteButton'],
            'showTrashButton' => ['showTrashButton'],
        ];
    }

    #[Test]
    #[DataProvider('booleanFlagProvider')]
    public function a_false_flag_is_stored_as_a_boolean_not_as_a_word(string $flag): void
    {
        $this->configure(["{$flag}=false"]);

        $stored = $this->storedConfig()['permissions'][$flag] ?? null;

        $this->assertSame(
            false,
            $stored,
            "`{$flag}=false` gravou ".var_export($stored, true).
            ' — e `(bool) "false"` em PHP e true, entao o botao continua na tela.'
        );
    }

    #[Test]
    #[DataProvider('booleanFlagProvider')]
    public function a_true_flag_is_stored_as_a_boolean_too(string $flag): void
    {
        // The counterpart: the fix must not turn the whole value into a boolean
        // cast that mangles the other five keys.
        $this->configure(["{$flag}=true"]);

        $this->assertSame(true, $this->storedConfig()['permissions'][$flag] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function gateNameProvider(): array
    {
        return [
            'create' => ['create', 'products.create'],
            'edit' => ['edit', 'products.update'],
            'delete' => ['delete', 'products.destroy'],
            'export' => ['export', 'products.export'],
            'restore' => ['restore', 'products.restore'],
        ];
    }

    #[Test]
    #[DataProvider('gateNameProvider')]
    public function a_gate_name_is_still_stored_as_the_string_it_is(string $key, string $gate): void
    {
        // These five always worked through the same line, and the fix must not
        // change them. `castValue()` returns anything that is not
        // true/false/null/numeric unchanged.
        $this->configure(["{$key}={$gate}"]);

        $this->assertSame($gate, $this->storedConfig()['permissions'][$key] ?? null);
    }

    /**
     * The rendered screen, which is where the bug was reported.
     *
     * Asserted on the HTML and not on `getEffectivePermissions()` — that method
     * is protected, and reaching around the visibility to read it would test a
     * different thing from "abro a tela e o botao continua la".
     */
    private function renderedToolbar(): string
    {
        // Montado com a CHAVE canonica, nao com a FQCN, porque e o que um route
        // real faz: `ptah:forge Catalog/Product` gera uma rota que monta o
        // BaseCrud com "Catalog/Product", e o ConfigCommand grava sob a mesma
        // chave. `ModelKey::canonical()` troca as barras, entao montar com a
        // FQCN aqui procurava uma linha que ninguem gravou — o componente
        // renderizava sem config, sem coluna e sem botao, e a asserticao passava
        // por motivo errado. Nao e defeito do produto: era o arranjo.
        return Livewire::test(BaseCrud::class, [
            'model' => ModelKey::canonical(PermissionCliStub::class),
        ])->html();
    }

    #[Test]
    public function the_button_actually_disappears_from_the_screen(): void
    {
        // The storage is not the point, the screen is. A test that only checked
        // the persisted type would pass while the button stayed.
        $before = $this->renderedToolbar();

        // Ancora: as duas ocorrencias de `prepareCreate` nas views (o botao da
        // barra e o atalho de teclado) sao gated por canCreate, entao a
        // presenca da string e um proxy fiel de "o criar esta disponivel". Sem
        // esta asserticao a de baixo passaria numa tela que nunca teve botao —
        // e foi assim que a primeira versao deste teste falhou, com config sem
        // coluna nenhuma.
        $this->assertStringContainsString(
            'prepareCreate',
            $before,
            'O criar tem de estar disponivel antes — senao o teste abaixo passa a vazio.'
        );

        $this->configure(['showCreateButton=false']);

        $this->assertStringNotContainsString(
            'prepareCreate',
            $this->renderedToolbar(),
            'O botao Novo continua na tela depois de showCreateButton=false.'
        );
    }

    #[Test]
    public function the_other_buttons_are_untouched_by_one_flag(): void
    {
        // Turning one off must not turn the rest off, which a blanket cast over
        // the whole permissions array could easily do.
        $this->configure(['showCreateButton=false']);

        $html = $this->renderedToolbar();

        $this->assertStringNotContainsString('prepareCreate', $html);
        // E a tela continua inteira: se a correcao tivesse virado um cast sobre
        // o array de permissoes todo, isto cairia junto.
        $this->assertStringContainsString('ptah-base-crud', $html, 'A listagem ainda tem de renderizar.');
        $this->assertStringContainsString('Nome', $html, 'A coluna configurada ainda tem de aparecer.');
    }

    #[Test]
    public function several_flags_in_one_call_all_arrive_typed(): void
    {
        $this->configure([
            'showCreateButton=false',
            'showEditButton=false',
            'edit=products.update',
        ]);

        $perms = $this->storedConfig()['permissions'] ?? [];

        $this->assertSame(false, $perms['showCreateButton'] ?? null);
        $this->assertSame(false, $perms['showEditButton'] ?? null);
        $this->assertSame('products.update', $perms['edit'] ?? null);
        // Not named, so it keeps the default.
        $this->assertArrayNotHasKey('showDeleteButton', $perms);
    }

    #[Test]
    public function the_import_path_that_already_worked_still_works(): void
    {
        // `--import` was the only way to reach these flags, because
        // `json_decode($json, true)` delivers a typed boolean. Pinned so the
        // escape hatch cannot be lost while fixing the flag.
        $file = sys_get_temp_dir().'/ptah-perm-'.uniqid().'.json';

        file_put_contents($file, (string) json_encode([
            'crud' => PermissionCliStub::class,
            'cols' => [],
            'permissions' => ['showCreateButton' => false],
        ]));

        try {
            $this->artisan('ptah:config', [
                'model' => PermissionCliStub::class,
                '--import' => $file,
                '--non-interactive' => true,
            ])->assertExitCode(0);

            $this->assertSame(false, $this->storedConfig()['permissions']['showCreateButton'] ?? null);
        } finally {
            @unlink($file);
        }
    }

    #[Test]
    public function set_and_permission_use_the_same_caster(): void
    {
        // The root cause was one of the two paths skipping the shared helper.
        // Read from the source: a comment saying they agree is not the same as
        // them agreeing.
        $source = (string) file_get_contents(__DIR__.'/../../../src/Commands/ConfigCommand.php');

        $this->assertSame(
            2,
            preg_match_all('/\$this->castValue\(\$\w+\)/', $source),
            'As duas flags que fazem explode(\'=\') precisam passar pelo castValue.'
        );
    }
}
