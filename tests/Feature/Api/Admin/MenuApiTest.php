<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Menu;
use Ptah\Models\Setting;
use Ptah\Services\SettingsService;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class MenuApiUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * The menu editor and the system settings through the admin API, for the
 * React and Vue stacks. MenuList and SettingsPage are the engine — these
 * tests pin the contract and that their guards hold through it.
 */
class MenuApiTest extends TestCase
{
    use ActsAsPtahUser;

    private const MENU = '/ptah/api/admin/menu';

    private const SETTINGS = '/ptah/api/admin/settings';

    private ?string $tmp = null;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        // Testbench applies #[DefineEnvironment] BEFORE this method, so the
        // one test without the module is named here instead.
        $app['config']->set('ptah.modules.menu', $this->name() !== 'without_the_menu_module_the_menu_endpoints_do_not_exist_but_the_settings_do');
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('auth.providers.users.model', MenuApiUser::class);
    }

    protected function tearDown(): void
    {
        if ($this->tmp !== null) {
            (new Filesystem)->deleteDirectory($this->tmp);
        }
        Setting::flushTableCache();

        parent::tearDown();
    }

    private function signIn(bool $master = true): void
    {
        $this->actAsMaster($master);
        $this->actingAs(MenuApiUser::firstOrCreate(['email' => 'ana@example.test'], ['name' => 'Ana', 'password' => Hash::make('secret-123')]));
    }

    private function item(array $extra = []): Menu
    {
        return Menu::create(array_merge(['text' => 'Pedidos', 'url' => '/orders', 'type' => 'menuLink', 'target' => '_self', 'link_order' => 1, 'is_active' => true], $extra));
    }

    // ── Menu: access ────────────────────────────────────────────────────────

    #[Test]
    public function a_guest_is_refused(): void
    {
        $this->getJson(self::MENU)->assertUnauthorized();
        $this->getJson(self::SETTINGS)->assertUnauthorized();
    }

    #[Test]
    public function a_user_who_is_not_master_is_refused_on_every_endpoint(): void
    {
        $menu = $this->item();
        $this->signIn(false);

        $this->getJson(self::MENU)->assertForbidden();
        $this->getJson(self::MENU.'/'.$menu->id)->assertForbidden();
        $this->postJson(self::MENU, ['text' => 'X'])->assertForbidden();
        $this->putJson(self::MENU.'/'.$menu->id, ['text' => 'X'])->assertForbidden();
        $this->postJson(self::MENU.'/'.$menu->id.'/toggle')->assertForbidden();
        $this->deleteJson(self::MENU.'/'.$menu->id)->assertForbidden();
        $this->getJson(self::SETTINGS)->assertForbidden();
        $this->putJson(self::SETTINGS, ['values' => []])->assertForbidden();

        $this->assertSame('Pedidos', $menu->fresh()->text);
        $this->assertTrue($menu->fresh()->is_active);
    }

    #[Test]
    public function without_the_menu_module_the_menu_endpoints_do_not_exist_but_the_settings_do(): void
    {
        // As /ptah-menu: loaded only with the module; /ptah-settings always.
        $this->signIn();

        $this->getJson(self::MENU)->assertNotFound();
        $this->getJson(self::SETTINGS)->assertOk();
    }

    // ── Menu: list ──────────────────────────────────────────────────────────

    #[Test]
    public function the_list_answers_rows_meta_state_and_groups(): void
    {
        $group = $this->item(['text' => 'Vendas', 'url' => null, 'type' => 'menuGroup', 'link_order' => 0]);
        $this->item(['text' => 'Pedidos', 'parent_id' => $group->id, 'link_order' => 2]);
        $this->item(['text' => 'Clientes', 'link_order' => 1, 'is_active' => false]);
        $this->signIn();

        $response = $this->getJson(self::MENU)->assertOk();

        $this->assertSame(['Vendas', 'Clientes', 'Pedidos'], array_column($response->json('data'), 'text'));
        $response->assertJsonPath('data.2.parent', 'Vendas')
            ->assertJsonPath('data.1.is_active', false)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('state.sort', 'link_order')
            ->assertJsonPath('groups.0.text', 'Vendas');
        $this->assertSame(['id', 'text', 'url', 'icon', 'type', 'target', 'parent_id', 'parent', 'link_order', 'is_active'], array_keys($response->json('data.0')));
    }

    #[Test]
    public function the_list_searches_filters_sorts_and_paginates(): void
    {
        $this->item(['text' => 'Alfa', 'link_order' => 3]);
        $this->item(['text' => 'Beta', 'link_order' => 2]);
        $this->item(['text' => 'Grupo', 'url' => null, 'type' => 'menuGroup', 'link_order' => 1]);
        $this->signIn();

        $this->assertSame(['Alfa'], array_column($this->getJson(self::MENU.'?search=alf')->json('data'), 'text'));
        $this->assertSame(['Grupo'], array_column($this->getJson(self::MENU.'?type=menuGroup')->json('data'), 'text'));
        $this->assertSame(['Grupo', 'Beta', 'Alfa'], array_column($this->getJson(self::MENU.'?sort=text&direction=desc')->json('data'), 'text'));

        for ($i = 0; $i < 20; $i++) {
            $this->item(['text' => 'Item '.$i, 'link_order' => 10 + $i]);
        }
        $second = $this->getJson(self::MENU.'?page=2')->assertOk();
        $second->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.last_page', 2);
        $this->assertCount(3, $second->json('data'));
    }

    #[Test]
    public function a_forged_sort_column_or_type_is_refused(): void
    {
        $this->signIn();

        // The Livewire property could be forged into any column for orderBy().
        $this->getJson(self::MENU.'?sort=password')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson(self::MENU.'?direction=sideways')->assertStatus(422)->assertJsonValidationErrors('direction');
        $this->getJson(self::MENU.'?type=evil')->assertStatus(422)->assertJsonValidationErrors('type');
    }

    // ── Menu: create, edit, toggle, delete ──────────────────────────────────

    #[Test]
    public function an_item_is_created_with_the_component_rules(): void
    {
        $this->signIn();

        $this->postJson(self::MENU, ['text' => ''])->assertStatus(422)
            ->assertJsonValidationErrors('text')
            ->assertJsonPath('errors.text.0', 'The menu text is required.');

        $group = $this->item(['text' => 'Vendas', 'url' => null, 'type' => 'menuGroup']);
        $this->postJson(self::MENU, [
            'text' => 'Relatórios', 'url' => '/reports', 'icon' => 'bx bx-bar-chart', 'type' => 'menuLink',
            'target' => '_blank', 'parent_id' => (string) $group->id, 'link_order' => 4, 'is_active' => true,
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('messages.0.level', 'success');

        $created = Menu::where('text', 'Relatórios')->firstOrFail();
        $this->assertSame('/reports', $created->url);
        $this->assertSame($group->id, $created->parent_id);
        $this->assertSame('_blank', $created->target);
        $this->assertSame(4, $created->link_order);
    }

    #[Test]
    public function a_javascript_url_is_refused(): void
    {
        $this->signIn();

        $this->postJson(self::MENU, ['text' => 'Xss', 'url' => 'javascript:alert(1)', 'type' => 'menuLink', 'target' => '_self'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->postJson(self::MENU, ['text' => 'Xss', 'type' => 'menuEvil', 'target' => '_top', 'parent_id' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['type', 'target', 'parent_id']);

        $this->assertSame(0, Menu::count());
    }

    #[Test]
    public function an_item_opens_for_editing_and_is_updated(): void
    {
        $group = $this->item(['text' => 'Vendas', 'url' => null, 'type' => 'menuGroup']);
        $menu = $this->item(['parent_id' => $group->id]);
        $this->signIn();

        $this->getJson(self::MENU.'/'.$menu->id)->assertOk()
            ->assertJsonPath('data.id', $menu->id)
            ->assertJsonPath('data.text', 'Pedidos')
            ->assertJsonPath('data.parent_id', $group->id)
            ->assertJsonPath('groups.0.id', $group->id);
        // A group never offers itself as its own parent.
        $this->assertSame([], $this->getJson(self::MENU.'/'.$group->id)->json('groups'));

        $this->putJson(self::MENU.'/'.$menu->id, ['text' => ''])->assertStatus(422)->assertJsonValidationErrors('text');

        // Only the fields sent change; the rest keeps the stored value.
        $this->putJson(self::MENU.'/'.$menu->id, ['text' => 'Pedidos de venda'])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('Pedidos de venda', $menu->fresh()->text);
        $this->assertSame('/orders', $menu->fresh()->url);
        $this->assertSame($group->id, $menu->fresh()->parent_id);

        // Becoming a group drops the URL, as the screen does.
        $this->putJson(self::MENU.'/'.$menu->id, ['type' => 'menuGroup', 'parent_id' => null])->assertOk();
        $this->assertNull($menu->fresh()->url);
        $this->assertNull($menu->fresh()->parent_id);

        $this->getJson(self::MENU.'/999')->assertNotFound();
        $this->putJson(self::MENU.'/999', ['text' => 'X'])->assertNotFound();
    }

    #[Test]
    public function an_item_is_toggled(): void
    {
        $menu = $this->item();
        $this->signIn();

        $this->postJson(self::MENU.'/'.$menu->id.'/toggle')->assertOk()->assertJsonPath('is_active', false);
        $this->assertFalse($menu->fresh()->is_active);
        $this->postJson(self::MENU.'/'.$menu->id.'/toggle')->assertOk()->assertJsonPath('is_active', true);
        $this->postJson(self::MENU.'/999/toggle')->assertNotFound();
    }

    #[Test]
    public function deleting_a_group_unlinks_its_children(): void
    {
        $group = $this->item(['text' => 'Vendas', 'url' => null, 'type' => 'menuGroup']);
        $child = $this->item(['parent_id' => $group->id]);
        $this->signIn();

        $this->deleteJson(self::MENU.'/'.$group->id)->assertOk()->assertJsonPath('ok', true);

        $this->assertSoftDeleted('menus', ['id' => $group->id]);
        $this->assertNull($child->fresh()->parent_id);

        // An id that is gone is the component's own failure toast.
        $this->deleteJson(self::MENU.'/'.$group->id)->assertStatus(422)->assertJsonPath('ok', false);
    }

    // ── Settings ────────────────────────────────────────────────────────────

    private function installSettings(): void
    {
        $this->tmp = sys_get_temp_dir().'/ptah-settings-api-'.uniqid();
        $this->app->useDatabasePath($this->tmp.'/database');
        $this->app->useConfigPath($this->tmp.'/config');
        $this->artisan('ptah:settings:install')->assertExitCode(0);
        foreach (glob($this->tmp.'/database/migrations/*.php') ?: [] as $file) {
            (require $file)->up();
        }
        Setting::flushTableCache();
    }

    private function declareSettings(): void
    {
        config(['ptah-settings.per_company' => true, 'ptah-settings.definitions' => [
            'invoice_due_days' => ['label' => 'Vencimento', 'type' => 'integer', 'group' => 'Financeiro', 'default' => 30, 'rules' => 'min:0|max:365', 'help' => 'Em dias.'],
            'approval' => ['label' => 'Exige aprovação', 'type' => 'boolean', 'group' => 'Vendas', 'default' => false],
            'carrier' => ['label' => 'Transportadora', 'type' => 'select', 'options' => ['correios' => 'Correios', 'jadlog' => 'Jadlog'], 'default' => 'correios'],
            'api_token' => ['label' => 'Token', 'type' => 'text', 'group' => 'Integração', 'secret' => true, 'default' => ''],
        ]]);
        session(['ptah_company_id' => 7]);
    }

    #[Test]
    public function without_the_table_the_settings_explain_how_to_install(): void
    {
        Setting::flushTableCache();
        $this->declareSettings();
        $this->signIn();

        $this->getJson(self::SETTINGS)->assertOk()->assertJsonPath('installed', false);
        $this->putJson(self::SETTINGS, ['values' => ['invoice_due_days' => 10]])
            ->assertStatus(409)
            ->assertJsonPath('installed', false)
            ->assertJsonPath('error', trans('ptah::ui.settings_not_installed'));
    }

    #[Test]
    public function the_settings_answer_groups_fields_and_scope_without_secrets(): void
    {
        $this->installSettings();
        $this->declareSettings();
        $this->signIn();
        app(SettingsService::class)->set('api_token', 'tok-super-secreto', 7);

        $response = $this->getJson(self::SETTINGS)->assertOk()
            ->assertJsonPath('installed', true)
            ->assertJsonPath('scope', 'company')
            ->assertJsonPath('per_company', true)
            ->assertJsonPath('can_edit_global', true)
            ->assertJsonPath('groups.0.name', 'Financeiro')
            ->assertJsonPath('groups.0.fields.0.key', 'invoice_due_days')
            ->assertJsonPath('groups.0.fields.0.value', 30)
            ->assertJsonPath('groups.0.fields.0.source', 'default')
            ->assertJsonPath('groups.0.fields.0.help', 'Em dias.');

        $this->assertStringNotContainsString('tok-super-secreto', $response->getContent());
        $general = collect($response->json('groups'))->firstWhere('name', 'General');
        $this->assertSame([['value' => 'correios', 'label' => 'Correios'], ['value' => 'jadlog', 'label' => 'Jadlog']], $general['fields'][0]['options']);
    }

    #[Test]
    public function settings_are_validated_and_saved_for_the_active_company(): void
    {
        $this->installSettings();
        $this->declareSettings();
        $this->signIn();
        app(SettingsService::class)->set('api_token', 'tok-super-secreto', 7);

        $this->putJson(self::SETTINGS, ['values' => ['invoice_due_days' => 400, 'carrier' => 'hacked']])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['errors' => ['invoice_due_days', 'carrier']]);
        $this->assertSame(30, ptah_setting('invoice_due_days'));

        $this->putJson(self::SETTINGS, ['scope' => 'company', 'values' => ['invoice_due_days' => 15, 'approval' => true, 'api_token' => '', 'not_declared' => 'x']])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.text', trans('ptah::ui.settings_saved'))
            ->assertJsonPath('settings.groups.0.fields.0.source', 'company');

        $this->assertSame(15, ptah_setting('invoice_due_days'));
        $this->assertTrue(ptah_setting('approval'));
        $this->assertSame(30, ptah_setting('invoice_due_days', null, 8), 'Saved for the active company only.');
        $this->assertSame('tok-super-secreto', ptah_setting('api_token', null, 7), 'A blank secret is "unchanged".');
        $this->assertSame(0, Setting::where('key', 'not_declared')->count());
    }

    #[Test]
    public function the_global_scope_is_edited_and_a_company_value_is_reset_to_it(): void
    {
        $this->installSettings();
        $this->declareSettings();
        $this->signIn();

        $this->getJson(self::SETTINGS.'?scope=global')->assertOk()->assertJsonPath('scope', 'global');
        $this->putJson(self::SETTINGS, ['scope' => 'global', 'values' => ['invoice_due_days' => 60]])->assertOk();
        $this->assertSame(60, ptah_setting('invoice_due_days', null, 8));

        $this->putJson(self::SETTINGS, ['scope' => 'company', 'values' => ['invoice_due_days' => 5]])->assertOk();
        $this->assertSame(5, ptah_setting('invoice_due_days'));

        $this->postJson(self::SETTINGS.'/invoice_due_days/reset', ['scope' => 'company'])
            ->assertOk()
            ->assertJsonPath('settings.groups.0.fields.0.source', 'global')
            ->assertJsonPath('settings.groups.0.fields.0.value', 60);
        $this->assertSame(60, ptah_setting('invoice_due_days'));

        $this->getJson(self::SETTINGS.'?scope=everywhere')->assertStatus(422)->assertJsonValidationErrors('scope');
    }

    #[Test]
    public function a_user_who_may_not_edit_the_global_values_is_refused_instead_of_saved_elsewhere(): void
    {
        // Without the permissions module the screen opens with PTAH_STRUCTURE_EDITOR;
        // editGlobal() then lets anyone in. With it, only a master — who is
        // also the only one past boot(). A refusal of editGlobal() must not
        // turn into a save into the company's values.
        $this->installSettings();
        $this->declareSettings();
        config(['ptah.modules.permissions' => false, 'ptah.structure_editor' => true]);
        $this->signIn(false);

        $this->putJson(self::SETTINGS, ['scope' => 'global', 'values' => ['invoice_due_days' => 99]])->assertOk();
        $this->assertSame(99, ptah_setting('invoice_due_days', null, 8));

        config(['ptah.modules.permissions' => true]);
        $this->signIn(false);
        $this->putJson(self::SETTINGS, ['scope' => 'global', 'values' => ['invoice_due_days' => 1]])->assertForbidden();
        $this->assertSame(99, ptah_setting('invoice_due_days', null, 8));
    }
}
