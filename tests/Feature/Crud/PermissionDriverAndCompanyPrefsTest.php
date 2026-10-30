<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Services\Export\ExportAuthorizer;
use Ptah\Support\CrudPermission;
use Ptah\Support\ScreenSummary;
use Ptah\Tests\TestCase;

class P156Item extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'amount'];
}

/**
 * 1.56.0: permissions through Laravel's Gate (Spatie & co.), and screen
 * preferences — page included — kept per company.
 */
class PermissionDriverAndCompanyPrefsTest extends TestCase
{
    private function user(): Model
    {
        $class = config('auth.providers.users.model');
        $user = $class::forceCreate(['name' => 'U', 'email' => 'u'.uniqid().'@t.com', 'password' => bcrypt('x')]);
        Auth::login($user);

        return $user;
    }

    private function configure(array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => P156Item::class, 'route' => ''], ['config' => array_merge([
            'crud' => P156Item::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Preco de custo', 'colsTipo' => 'number', 'colsGravar' => true, 'colsPermission' => 'items.cost'],
            ],
            'permissions' => ['permissionIdentifier' => 'items'],
        ], $extra)]);
    }

    private function crud(array $params = [])
    {
        return Livewire::test(BaseCrud::class, array_merge(['model' => P156Item::class], $params));
    }

    // ── permission driver ────────────────────────────────────────────────

    #[Test]
    public function the_gate_driver_enforces_the_screen_without_ptahs_module(): void
    {
        config(['ptah.modules.permissions' => false, 'ptah.permissions.driver' => 'gate']);
        $this->user();
        $this->configure();
        P156Item::create(['name' => 'Alfa', 'amount' => 7]);

        Gate::define('items.read', fn () => false);
        $this->crud()->assertStatus(403);

        Gate::define('items.read', fn () => true);
        Gate::define('items.cost', fn () => false);
        $this->crud()->assertStatus(200)->assertSee('Alfa')->assertDontSee('Preco de custo');

        Gate::define('items.cost', fn () => true);
        $this->crud()->assertSee('Preco de custo');
    }

    #[Test]
    public function the_gate_driver_gates_actions_and_exports(): void
    {
        config(['ptah.permissions.driver' => 'gate']);
        $user = $this->user();
        $this->configure();
        Gate::define('items.read', fn () => true);
        Gate::define('items.create', fn () => false);

        $this->crud()->call('openCreate')->set('formData.name', 'Novo')->call('save');
        $this->assertSame(0, P156Item::count(), 'Sem items.create o save e recusado.');

        $this->assertNull(app(ExportAuthorizer::class)->reasonDenied(P156Item::class, $user));
        Gate::define('items.read', fn () => false);
        $this->assertNotNull(app(ExportAuthorizer::class)->reasonDenied(P156Item::class, $user));
    }

    #[Test]
    public function the_ability_name_follows_the_configured_pattern(): void
    {
        config(['ptah.permissions.gate_ability' => '{action} {identifier}']);
        $this->assertSame('update products', CrudPermission::ability('products', 'update'));

        config(['ptah.permissions.gate_ability' => null]);
        $this->assertSame('products.update', CrudPermission::ability('products', 'update'));
    }

    #[Test]
    public function the_ptah_driver_is_unchanged_without_its_module(): void
    {
        config(['ptah.modules.permissions' => false, 'ptah.permissions.driver' => 'ptah']);
        $this->user();
        $this->configure();
        P156Item::create(['name' => 'Alfa', 'amount' => 7]);
        Gate::define('items.read', fn () => false);

        $this->crud()->assertStatus(200)->assertSee('Preco de custo');
    }

    #[Test]
    public function ptah_screen_says_how_the_permission_is_checked(): void
    {
        $summary = fn () => ScreenSummary::toText(ScreenSummary::build('Product', '', ['permissions' => ['permissionIdentifier' => 'products']]));

        config(['ptah.permissions.driver' => 'ptah', 'ptah.modules.permissions' => false]);
        $this->assertStringContainsString('permission: products  (NOT enforced', $summary());

        config(['ptah.modules.permissions' => true]);
        $this->assertStringContainsString('(ptah ACL)', $summary());

        config(['ptah.permissions.driver' => 'gate']);
        $this->assertStringContainsString('(Gate: products.read, products.update…)', $summary());
    }

    // ── preferences per company ──────────────────────────────────────────

    private function seedRows(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            P156Item::create(['name' => sprintf('Item %03d', $i)]);
        }
    }

    #[Test]
    public function each_company_keeps_its_own_page_and_filters(): void
    {
        config(['ptah.modules.company' => true]);
        $user = $this->user();
        $this->configure(['permissions' => []]);
        $this->seedRows(60);

        $this->crud(['companyFilter' => 1])->call('gotoPage', 3);
        $this->crud(['companyFilter' => 3])->set('search', 'Item 00');

        $this->assertSame(3, UserPreference::get($user->id, 'crud.'.P156Item::class.'@company:1')['table']['currentPage']);
        $this->assertSame('Item 00', UserPreference::get($user->id, 'crud.'.P156Item::class.'@company:3')['filters']['search']);

        $this->crud(['companyFilter' => 1])->assertSet('paginators.page', 3)->assertSet('search', '');
        $this->crud(['companyFilter' => 3])->assertSet('search', 'Item 00');
    }

    #[Test]
    public function a_company_with_no_preferences_starts_from_the_old_ones(): void
    {
        config(['ptah.modules.company' => true]);
        $user = $this->user();
        $this->configure(['permissions' => []]);
        UserPreference::set($user->id, 'crud.'.P156Item::class, ['filters' => ['search' => 'legado'], 'table' => ['currentPage' => 1]], 'crud');

        $this->crud(['companyFilter' => 5])->assertSet('search', 'legado');
    }

    #[Test]
    public function a_saved_page_past_the_last_one_shows_the_last_page(): void
    {
        config(['ptah.modules.company' => true]);
        $user = $this->user();
        $this->configure(['permissions' => []]);
        $this->seedRows(30);
        UserPreference::set($user->id, 'crud.'.P156Item::class.'@company:1', ['table' => ['currentPage' => 20, 'perPage' => 25]], 'crud');

        $crud = $this->crud(['companyFilter' => 1]);

        $crud->assertSet('paginators.page', 2)->assertSee('Item 001')->assertDontSee('Item 030');
    }
}
