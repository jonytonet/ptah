<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Services\Auth\TwoFactorService;
use Ptah\Services\DashboardService;
use Ptah\Tests\TestCase;

class AuditLowDoc extends Model
{
    use SoftDeletes;

    protected $table = 'audit_low_docs';

    protected $fillable = ['title', 'status', 'company_id'];
}

class AuditLowMissingTable extends Model
{
    protected $table = 'nao_existe_esta_tabela';
}

/**
 * The LOW findings of the 28/09/2026 surface audit, fixed in 1.41.11.
 */
class AuditLowFindingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_low_docs', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('status')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        $this->screen();
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function screen(array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => AuditLowDoc::class, 'route' => ''], ['config' => [
            'crud' => AuditLowDoc::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Aberto' => 'open', 'Fechado' => 'closed']],
            ],
            'permissions' => $permissions,
        ]]);
    }

    private function crud(int $company = 0)
    {
        return Livewire::test(BaseCrud::class, ['model' => AuditLowDoc::class, 'companyFilter' => $company]);
    }

    #[Test]
    public function per_page_is_capped(): void
    {
        $rows = $this->crud()->set('perPage', 1000000)->viewData('rows');

        $this->assertSame(200, $rows->perPage());
    }

    #[Test]
    public function the_trash_is_only_where_the_screen_offers_it(): void
    {
        AuditLowDoc::create(['title' => 'Excluido'])->delete();
        $this->screen(['showTrashButton' => false]);

        $rows = $this->crud()->set('showTrashed', true)->viewData('rows');
        $this->assertSame(0, $rows->total(), 'A lixeira apareceu numa tela que nao a oferece.');

        $this->crud()->call('toggleTrashed')->assertSet('showTrashed', false);
    }

    #[Test]
    public function the_trash_count_stays_inside_the_company(): void
    {
        AuditLowDoc::create(['title' => 'Minha', 'company_id' => 1])->delete();
        AuditLowDoc::create(['title' => 'Deles', 'company_id' => 2])->delete();

        $this->assertSame(1, $this->crud(company: 1)->get('trashedCount'));
    }

    #[Test]
    public function a_select_value_must_be_one_of_the_options(): void
    {
        $crud = $this->crud()->call('openCreate')->set('formData.title', 'Doc')->set('formData.status', 'hacked')->call('save');

        $this->assertArrayHasKey('status', $crud->get('formErrors'));
        $this->assertSame(0, AuditLowDoc::count());
    }

    #[Test]
    public function a_widget_that_breaks_does_not_show_the_sql_outside_debug(): void
    {
        config(['app.debug' => false]);

        $error = (string) app(DashboardService::class)->compute(['type' => 'stat', 'model' => AuditLowMissingTable::class, 'cache' => 0])['error'];

        $this->assertStringNotContainsString('nao_existe_esta_tabela', $error);
        $this->assertSame(trans('ptah::ui.dashboard_widget_error'), $error);
    }

    #[Test]
    public function the_email_code_can_be_invalidated(): void
    {
        $user = new User;
        $user->id = 9;
        Cache::put('ptah_2fa_email_9', '123456', 600);

        app(TwoFactorService::class)->forgetEmailCode($user);

        $this->assertFalse(app(TwoFactorService::class)->verifyEmailCode($user, '123456'));
    }
}
