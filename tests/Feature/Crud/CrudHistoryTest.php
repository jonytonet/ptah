<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\RecordHistory;
use Ptah\Tests\TestCase;
use Ptah\Traits\RecordsHistory;

class HistUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class HistProduct extends Model
{
    use RecordsHistory;

    protected $table = 'hist_products';

    protected $fillable = ['name', 'status', 'cost', 'secret', 'company_id'];

    protected $hidden = ['secret'];
}

/**
 * Record history — the table is installed the way a host installs it (the
 * command's migration, run), changes are recorded on the MODEL, and the
 * screen shows only what the screen may show.
 */
class CrudHistoryTest extends TestCase
{
    private string $migrations;

    private HistUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => HistUser::class]);

        $this->migrations = sys_get_temp_dir().'/ptah-hist-'.uniqid();
        $this->app->useDatabasePath($this->migrations);
        $this->artisan('ptah:history:install')->assertExitCode(0);
        foreach (glob($this->migrations.'/migrations/*.php') as $file) {
            (require $file)->up();
        }
        RecordHistory::flushTableCache();

        Schema::create('hist_products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status');
            $t->decimal('cost', 10, 2)->nullable();
            $t->string('secret')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        CrudConfig::create(['model' => HistProduct::class, 'route' => '', 'config' => [
            'crud' => HistProduct::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive']],
            ],
            'permissions' => [],
        ]]);

        $this->user = HistUser::create(['name' => 'Ana Souza', 'email' => 'ana@example.com', 'password' => 'x']);
        $this->actingAs($this->user);
        session(['ptah_company_id' => 1]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->migrations);
        RecordHistory::flushTableCache();

        parent::tearDown();
    }

    #[Test]
    public function the_install_command_is_idempotent(): void
    {
        $this->artisan('ptah:history:install')->expectsOutputToContain('Already installed')->assertExitCode(0);
        $this->assertCount(1, glob($this->migrations.'/migrations/*_create_ptah_record_history_table.php'));
    }

    #[Test]
    public function the_model_records_what_changed_and_never_the_hidden_attributes(): void
    {
        $p = HistProduct::create(['name' => 'Parafuso', 'status' => 'active', 'secret' => 'hash-1', 'company_id' => 1]);
        $p->update(['name' => 'Parafuso M8', 'secret' => 'hash-2']);
        $p->update(['name' => 'Parafuso M8']); // nada mudou: nada gravado
        $p->delete();

        $rows = RecordHistory::query()->orderBy('id')->get();

        $this->assertSame(['created', 'updated', 'deleted'], $rows->pluck('event')->all());
        $this->assertSame(['Parafuso', 'Parafuso M8'], $rows[1]->changes['name']);
        $this->assertArrayNotHasKey('secret', $rows[0]->changes, 'Atributo $hidden entrou no historico.');
        $this->assertArrayNotHasKey('secret', $rows[1]->changes ?? []);
        $this->assertArrayNotHasKey('updated_at', $rows[1]->changes);
        $this->assertSame($this->user->id, $rows[1]->user_id);
        $this->assertSame('web', $rows[1]->user_guard);
        $this->assertSame(1, $rows[1]->company_id);
    }

    #[Test]
    public function a_missing_table_never_breaks_the_save(): void
    {
        Schema::drop(RecordHistory::TABLE);
        RecordHistory::flushTableCache();

        $p = HistProduct::create(['name' => 'Sem historico', 'status' => 'active']);

        $this->assertTrue($p->exists);
    }

    #[Test]
    public function the_screen_shows_labels_and_hides_what_it_does_not_show(): void
    {
        $p = HistProduct::create(['name' => 'Porca', 'status' => 'inactive', 'cost' => 1.5, 'company_id' => 1]);
        $p->update(['status' => 'active', 'cost' => 2.0]);

        $crud = Livewire::test(BaseCrud::class, ['model' => HistProduct::class])
            ->call('openEdit', $p->id)
            ->assertSee(__('ptah::ui.btn_history'))
            ->call('openHistory', $p->id)
            ->assertSet('showHistoryModal', true);

        $items = $crud->get('historyItems');
        $update = $items[0];

        $this->assertSame('updated', $update['event']);
        $this->assertSame('Ana Souza', $update['who']);
        $this->assertSame([['label' => 'Situação', 'old' => 'Inativo', 'new' => 'Ativo']], $update['changes'], 'O select deveria aparecer pelo rotulo.');
        $this->assertSame(1, $update['hidden'], '`cost` nao esta na tela: contado, nao mostrado.');
        $crud->assertDontSee('2.0');
    }

    #[Test]
    public function another_companys_record_history_is_not_reachable(): void
    {
        $other = HistProduct::create(['name' => 'De outra empresa', 'status' => 'active', 'company_id' => 2]);

        Livewire::test(BaseCrud::class, ['model' => HistProduct::class])
            ->call('openHistory', $other->id)
            ->assertSet('showHistoryModal', false)
            ->assertSet('historyItems', []);
    }

    #[Test]
    public function the_history_gate_is_enforced(): void
    {
        $config = CrudConfig::where('model', HistProduct::class)->first();
        $config->update(['config' => array_merge($config->config, ['permissions' => ['history' => 'products.history']])]);
        $p = HistProduct::create(['name' => 'Porca', 'status' => 'active', 'company_id' => 1]);

        Livewire::test(BaseCrud::class, ['model' => HistProduct::class])
            ->call('openHistory', $p->id)
            ->assertSet('showHistoryModal', false);
    }

    #[Test]
    public function ptah_check_warns_when_the_trait_has_no_table(): void
    {
        Schema::drop(RecordHistory::TABLE);
        RecordHistory::flushTableCache();

        $this->artisan('ptah:check', ['model' => 'HistProduct'])
            ->expectsOutputToContain('uses RecordsHistory but table ptah_record_history does not exist')
            ->assertExitCode(0);
    }
}
