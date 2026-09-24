<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class GateStub extends Model
{
    use SoftDeletes;

    protected $table = 'gate_items';

    protected $fillable = ['name'];
}

class GateUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * The config's own access control was enforced by the interface only.
 *
 * A CRUD config carries access control of its own, independent of the RBAC
 * module: the `show*Button` flags, and Laravel Gate names in
 * `permissions.create`, `.edit`, `.delete`, `.export` and `.restore`.
 * `getEffectivePermissions()` read them — and used them to HIDE the button. The
 * actions consulted `ptah_can()` alone, which returns true when there is no
 * `permissionIdentifier` or the module is off.
 *
 * So a host that configured `permissions.delete = 'delete-products'`, or
 * `showDeleteButton = false`, saw the button disappear and reasonably believed
 * deletion was protected. A forged `confirmDelete(5)` + `deleteRecord()` — or
 * `bulkForceDelete()` — deleted anyway. `permissions.export` and `.restore`
 * were written by the config editor and read by nothing at all.
 *
 * This is the "the host thinks it protected, and did not" case, which is worse
 * than an open door: nobody goes looking.
 */
class CrudConfigGateEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('gate_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
            $table->timestamps();
        });

        // Sem permissionIdentifier: o ptah_can() libera, e a config e a unica
        // coisa entre o usuario e a acao — o cenario exato do defeito.
        $this->actingAs(GateUser::forceCreate([
            'name' => 'Operador',
            'email' => 'op@example.com',
            'password' => 'x',
        ]));
    }

    /**
     * @param  array<string, mixed>  $permissions
     */
    private function configure(array $permissions, bool $export = false): void
    {
        CrudConfig::updateOrCreate(
            ['model' => GateStub::class, 'route' => ''],
            ['config' => [
                'crud' => GateStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true],
                ],
                'permissions' => $permissions,
                'exportConfig' => ['enabled' => $export],
            ]]
        );
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => GateStub::class]);
    }

    private function denyGate(string $name): void
    {
        Gate::define($name, fn () => false);
    }

    // ── Delete ─────────────────────────────────────────────────────────────

    #[Test]
    public function a_gate_that_denies_deletion_stops_the_deletion(): void
    {
        $this->denyGate('delete-items');
        $this->configure(['delete' => 'delete-items']);
        $row = GateStub::create(['name' => 'protegido']);

        $this->crud()->call('confirmDelete', $row->id)->call('deleteRecord');

        $this->assertNotSoftDeleted('gate_items', ['id' => $row->id]);
    }

    #[Test]
    public function a_hidden_delete_button_is_not_a_delete_that_still_works(): void
    {
        $this->configure(['showDeleteButton' => false]);
        $row = GateStub::create(['name' => 'protegido']);

        $this->crud()->call('confirmDelete', $row->id)->call('deleteRecord');

        $this->assertNotSoftDeleted('gate_items', ['id' => $row->id]);
    }

    #[Test]
    public function bulk_delete_and_force_delete_are_held_to_the_same_rule(): void
    {
        $this->configure(['showDeleteButton' => false]);
        $row = GateStub::create(['name' => 'protegido']);

        $this->crud()->set('selectedRows', [(string) $row->id])->call('bulkDelete');
        $this->assertNotSoftDeleted('gate_items', ['id' => $row->id]);

        $row->delete();

        $this->crud()->set('showTrashed', true)->set('selectedRows', [(string) $row->id])->call('bulkForceDelete');
        $this->assertSoftDeleted('gate_items', ['id' => $row->id]);
    }

    #[Test]
    public function a_gate_that_allows_still_lets_the_deletion_through(): void
    {
        // A contrapartida: a regra nao pode ter virado "nega sempre".
        Gate::define('delete-items', fn () => true);
        $this->configure(['delete' => 'delete-items']);
        $row = GateStub::create(['name' => 'livre']);

        $this->crud()->call('confirmDelete', $row->id)->call('deleteRecord');

        $this->assertSoftDeleted('gate_items', ['id' => $row->id]);
    }

    // ── Create / update ────────────────────────────────────────────────────

    #[Test]
    public function a_hidden_create_button_stops_a_forged_create(): void
    {
        $this->configure(['showCreateButton' => false]);

        $this->crud()->set('formData.name', 'forjado')->call('save');

        $this->assertDatabaseMissing('gate_items', ['name' => 'forjado']);
    }

    #[Test]
    public function a_gate_that_denies_editing_stops_a_forged_update(): void
    {
        $this->denyGate('edit-items');
        $this->configure(['edit' => 'edit-items']);
        $row = GateStub::create(['name' => 'original']);

        $this->crud()
            ->set('editingId', $row->id)
            ->set('formData', ['name' => 'alterado'])
            ->call('save');

        $this->assertDatabaseHas('gate_items', ['id' => $row->id, 'name' => 'original']);
    }

    // ── Restore and export: keys that nothing read ─────────────────────────

    #[Test]
    public function permissions_restore_is_now_enforced(): void
    {
        $this->denyGate('restore-items');
        $this->configure(['restore' => 'restore-items']);
        $row = GateStub::create(['name' => 'na lixeira']);
        $row->delete();

        $this->crud()->call('restoreRecord', $row->id);

        $this->assertSoftDeleted('gate_items', ['id' => $row->id]);
    }

    #[Test]
    public function permissions_export_is_now_enforced(): void
    {
        $this->denyGate('export-items');
        $this->configure(['export' => 'export-items'], export: true);
        GateStub::create(['name' => 'dado']);

        $this->crud()->call('export')->assertNotDispatched('ptah:export-download');
    }

    #[Test]
    public function an_export_that_is_allowed_still_exports(): void
    {
        Gate::define('export-items', fn () => true);
        $this->configure(['export' => 'export-items'], export: true);
        GateStub::create(['name' => 'dado']);

        $this->crud()->call('export')->assertDispatched('ptah:export-download');
    }

    // ── The string "false" written before 1.34.2 ───────────────────────────

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function offFlagProvider(): array
    {
        return [
            'bool false' => [false],
            // Gravado pelo `--permission` antes da 1.34.2 — `(bool) "false"` e
            // true em PHP, e o botao continuava ativo.
            'string "false"' => ['false'],
            'string "0"' => ['0'],
            'int 0' => [0],
        ];
    }

    #[Test]
    #[DataProvider('offFlagProvider')]
    public function every_spelling_of_off_is_read_as_off(mixed $off): void
    {
        $this->configure(['showDeleteButton' => $off]);
        $row = GateStub::create(['name' => 'protegido']);

        $this->crud()->call('confirmDelete', $row->id)->call('deleteRecord');

        $this->assertNotSoftDeleted('gate_items', ['id' => $row->id]);
    }

    // ── The interface and the actions cannot drift apart again ─────────────

    #[Test]
    public function every_guarded_action_consults_the_config_as_well_as_the_rbac(): void
    {
        // A causa raiz foi a interface e as acoes lerem a config por caminhos
        // diferentes. Este guard le o codigo: toda chamada a
        // authorizeCrudAction() tem de vir acompanhada de crudConfigAllows()
        // na mesma condicao — salvo a acao em massa CUSTOMIZADA, que nao tem
        // flag de config que a descreva.
        $offenders = [];

        foreach (glob(__DIR__.'/../../../src/Livewire/BaseCrud/Concerns/*.php') ?: [] as $file) {
            $lines = explode("\n", (string) file_get_contents($file));

            foreach ($lines as $i => $line) {
                if (! str_contains($line, '$this->authorizeCrudAction(')) {
                    continue;
                }

                $statement = $line.($lines[$i + 1] ?? '');

                if (str_contains($statement, 'crudConfigAllows(')) {
                    continue;
                }

                // A acao customizada: a unica isencao, e so ela.
                $context = implode("\n", array_slice($lines, max(0, $i - 40), 40));
                if (str_contains($context, 'function executeBulkAction')) {
                    continue;
                }

                $offenders[] = basename($file).':'.($i + 1);
            }
        }

        $this->assertSame([], $offenders, "Acao que consulta o RBAC mas ignora a config:\n  ".implode("\n  ", $offenders));
    }
}
