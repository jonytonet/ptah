<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Auth\ProfilePage;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class AuditDoc extends Model
{
    protected $table = 'audit_docs';

    protected $fillable = ['title', 'secret', 'company_id', 'parent_id', 'client_id'];

    protected $hidden = ['secret'];
}

class AuditClient extends Model
{
    protected $table = 'audit_clients';

    protected $fillable = ['name', 'company_id'];
}

class AuditProfileUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * The HIGH findings of the 28/09/2026 surface audit, fixed in 1.41.7. Each
 * case below was reproducible on 1.41.6.
 */
class AuditHighFindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_docs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('secret')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->unsignedBigInteger('client_id')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        CrudConfig::updateOrCreate(['model' => AuditDoc::class, 'route' => ''], ['config' => [
            'crud' => AuditDoc::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'company_id', 'colsNomeLogico' => 'Empresa', 'colsTipo' => 'number', 'colsGravar' => true],
                ['colsNomeFisico' => 'parent_id', 'colsNomeLogico' => 'Pai', 'colsTipo' => 'number', 'colsGravar' => true],
                ['colsNomeFisico' => 'client_id', 'colsNomeLogico' => 'Cliente', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsSDModel' => AuditClient::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id'],
            ],
            'permissions' => [],
        ]]);

        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function crud(int $company = 0, array $locked = [])
    {
        return Livewire::test(BaseCrud::class, ['model' => AuditDoc::class, 'companyFilter' => $company, 'lockedFilters' => $locked]);
    }

    #[Test]
    public function a_date_range_on_a_hidden_column_is_ignored(): void
    {
        // O hash de senha era extraivel por busca binaria: o filtro de data
        // cai num whereBetween cru quando os limites nao sao datas.
        AuditDoc::create(['title' => 'A', 'secret' => 'bbb']);
        AuditDoc::create(['title' => 'B', 'secret' => 'yyy']);

        $rows = $this->crud()->set('dateRanges', ['secret_start' => 'a', 'secret_end' => 'c'])->viewData('rows');

        $this->assertSame(2, $rows->total(), 'O range sobre a coluna $hidden filtrou as linhas — o oraculo continua aberto.');
    }

    #[Test]
    public function a_save_cannot_move_a_record_to_another_company(): void
    {
        $doc = AuditDoc::create(['title' => 'Meu', 'company_id' => 1]);

        $this->crud(company: 1)->call('openEdit', $doc->id)->set('formData.company_id', 2)->call('save');

        $this->assertSame(1, (int) $doc->fresh()->company_id);
    }

    #[Test]
    public function a_detail_row_cannot_be_attached_to_another_parent(): void
    {
        $this->crud(locked: ['parent_id' => 5])
            ->call('openCreate')->set('formData.title', 'Filho')->set('formData.parent_id', 9)->call('save');

        $this->assertSame(5, (int) AuditDoc::where('title', 'Filho')->value('parent_id'));
    }

    #[Test]
    public function the_search_dropdown_lists_only_the_active_company_and_refuses_a_forged_value(): void
    {
        $mine = AuditClient::create(['name' => 'Ana Minha', 'company_id' => 1]);
        $shared = AuditClient::create(['name' => 'Ana Global', 'company_id' => null]);
        $theirs = AuditClient::create(['name' => 'Ana Deles', 'company_id' => 2]);

        $crud = $this->crud(company: 1)->call('searchDropdown', 'client_id', 'ana');
        $labels = array_column($crud->get('sdResults')['client_id'] ?? [], 'label');
        sort($labels);

        $this->assertSame(['Ana Global', 'Ana Minha'], $labels);

        $crud->call('openCreate')->set('formData.title', 'Pedido')->set('formData.client_id', $theirs->id)->call('save');
        $this->assertSame(0, AuditDoc::where('title', 'Pedido')->count(), 'Gravou FK para cliente de outra empresa.');
        $this->assertArrayHasKey('client_id', $crud->get('formErrors'));

        $crud->set('formData.client_id', $mine->id)->call('save');
        $this->assertSame(1, AuditDoc::where('title', 'Pedido')->count());
        $this->assertNotNull($shared);
    }

    #[Test]
    public function changing_the_profile_email_asks_for_the_current_password(): void
    {
        $user = AuditProfileUser::create(['name' => 'Ana', 'email' => 'ana@x.test', 'password' => bcrypt('secret-123')]);
        AuditProfileUser::create(['name' => 'Bia', 'email' => 'bia@x.test', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        Livewire::test(ProfilePage::class)->set('email', 'atacante@evil.test')->call('saveProfile')
            ->assertHasErrors('email_password');
        $this->assertSame('ana@x.test', $user->fresh()->email);

        Livewire::test(ProfilePage::class)->set('email', 'bia@x.test')->set('email_password', 'secret-123')->call('saveProfile')
            ->assertHasErrors('email');
        $this->assertSame('ana@x.test', $user->fresh()->email);

        Livewire::test(ProfilePage::class)->set('email', 'ana.nova@x.test')->set('email_password', 'secret-123')->call('saveProfile')
            ->assertHasNoErrors();
        $this->assertSame('ana.nova@x.test', $user->fresh()->email);

        // Trocar so o nome nao pede senha.
        Livewire::test(ProfilePage::class)->set('name', 'Ana Paula')->call('saveProfile')->assertHasNoErrors();
        $this->assertSame('Ana Paula', $user->fresh()->name);
    }
}
