<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class CrudConfigApiItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * The BaseCrud's visual config editor through the headless API, for the React
 * and Vue stacks. The Livewire component CrudConfig is the engine — these
 * tests pin the contract and that its rules and its gate hold through it.
 */
class CrudConfigApiTest extends TestCase
{
    use ActsAsPtahUser;

    private const URL = '/ptah/api/crud/items/config';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9]));
        $this->actAsMaster();

        CrudScreen::register('items', CrudConfigApiItem::class);

        CrudConfig::updateOrCreate(['model' => CrudConfigApiItem::class, 'route' => 'items'], ['config' => [
            'crud' => CrudConfigApiItem::class,
            'displayName' => 'Itens',
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Ativo' => 'active', 'Arquivado' => 'archived']],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true],
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Ver', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/items/%id%', 'colsGravar' => false],
            ],
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]],
            'permissions' => ['history' => true],
        ]]);
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return $this->getJson(self::URL)->assertOk()->json('document');
    }

    private function stored(): array
    {
        return CrudConfig::where('model', CrudConfigApiItem::class)->where('route', 'items')->firstOrFail()->config;
    }

    // ── Access ──────────────────────────────────────────────────────────────

    #[Test]
    public function a_guest_is_refused_even_when_the_api_middleware_has_no_auth(): void
    {
        // The flag-only gate (module off) cannot tell a guest from a user.
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);
        $this->app['auth']->forgetGuards();

        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, ['document' => ['settings' => ['displayName' => 'Hacked']]])->assertUnauthorized();
        $this->assertSame('Itens', $this->stored()['displayName']);
    }

    #[Test]
    public function an_unknown_screen_is_404_and_the_client_never_names_a_model(): void
    {
        $this->getJson('/ptah/api/crud/nope/config')->assertNotFound();
        $this->getJson('/ptah/api/crud/nope/config/can')->assertOk()->assertJson(['can' => false]);
    }

    #[Test]
    public function a_user_the_editor_refuses_gets_403_everywhere_and_nothing_is_written(): void
    {
        // Module off, editor flag off: the screen is readable, the editor is not.
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', false);
        $this->actAsUserWhoCan(false);

        $this->getJson(self::URL)->assertForbidden();
        $this->getJson(self::URL.'/can')->assertOk()->assertJson(['can' => false]);
        $this->putJson(self::URL, ['document' => ['settings' => ['displayName' => 'Hacked']]])->assertForbidden();
        $this->postJson(self::URL.'/actions/addField', ['form' => ['colsNomeFisico' => 'x']])->assertForbidden();
        $this->postJson(self::URL.'/preview', [])->assertForbidden();
        $this->postJson(self::URL.'/audience-count', [])->assertForbidden();

        $this->assertSame('Itens', $this->stored()['displayName']);
    }

    #[Test]
    public function a_non_master_without_the_crud_config_grant_is_refused_with_the_module_on(): void
    {
        $this->actAsUserWhoCan(false);

        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, ['document' => ['settings' => ['displayName' => 'Hacked']]])->assertForbidden();
        $this->assertSame('Itens', $this->stored()['displayName']);
    }

    #[Test]
    public function the_opt_in_flag_opens_the_editor_with_the_module_off(): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.crud.config_editor', true);
        $this->actAsUserWhoCan(false);

        $this->getJson(self::URL)->assertOk()->assertJson(['can' => true]);
        // The gear follows the same gate (it asked for a `configCrud` grant
        // nothing else knows, hiding the button from who could open the editor).
        $this->getJson(self::URL.'/can')->assertOk()->assertJson(['can' => true]);
    }

    #[Test]
    public function a_master_may_open_the_editor_and_sees_the_gear(): void
    {
        $this->getJson(self::URL.'/can')->assertOk()->assertJson(['can' => true]);
    }

    // ── Contract ────────────────────────────────────────────────────────────

    #[Test]
    public function the_document_is_the_editor_state_for_the_screen_route(): void
    {
        $json = $this->getJson(self::URL)->assertOk()->json();

        $this->assertSame('items', $json['screen']);
        $this->assertSame('items', $json['route']);
        $this->assertSame(['name', 'status', 'amount', 'id'], array_column($json['document']['columns'], 'colsNomeFisico'));
        // As the Livewire editor holds them: colsSelect as editable text, the totalizer per column.
        $this->assertSame('Ativo;active;;Arquivado;archived', $json['document']['columns'][1]['colsSelect']);
        $this->assertTrue($json['document']['columns'][2]['totalizadorEnabled']);
        $this->assertSame('Itens', $json['document']['settings']['displayName']);
        $this->assertSame('pageCrudConfigApiItem', $json['document']['settings']['permissionIdentifier']);
        $this->assertContains('searchdropdown', $json['choices']['fieldTypes']);
        $this->assertContains('money_brl', $json['choices']['masks']);
        $this->assertSame('page-crud-config-api-item-observer', $json['options']['broadcast']['defaultChannel']);
        $this->assertContains('id', $json['hints']['placeholders']);

        // Named keys only: the component's internals do not leave.
        foreach (['showModal', 'formDataField', 'configService', 'editingFieldIndex'] as $internal) {
            $this->assertArrayNotHasKey($internal, $json);
            $this->assertArrayNotHasKey($internal, $json['document']);
        }
    }

    #[Test]
    public function add_field_runs_the_component_defaults_and_join_rules(): void
    {
        $document = $this->document();
        $document['joins'] = [['type' => 'left', 'table' => 'suppliers', 'first' => 'items.supplier_id', 'second' => 'suppliers.id', 'distinct' => false,
            'select' => [['column' => 'suppliers.name', 'alias' => 'supplier_name']]]];

        $json = $this->postJson(self::URL.'/actions/addField', ['document' => $document, 'form' => ['colsNomeFisico' => 'supplier_name']])
            ->assertOk()->json();

        $added = end($json['document']['columns']);
        $this->assertSame('supplier_name', $added['colsNomeFisico']);
        $this->assertSame('Supplier_name', $added['colsNomeLogico']);
        $this->assertSame('text-start', $added['colsAlign']);
        // resolveJoinDefaults(): a JOIN alias is never written, and reads its qualified column.
        $this->assertFalse($added['colsGravar']);
        $this->assertSame('suppliers.name', $added['colsSource']);
        $this->assertTrue($json['applied']);
        $this->assertSame([], (array) $json['form']);

        // Nothing to add without a physical name — a no-op, as in Livewire.
        $json = $this->postJson(self::URL.'/actions/addField', ['document' => $document, 'form' => ['colsNomeLogico' => 'Sem nome']])->assertOk()->json();
        $this->assertFalse($json['applied']);
        $this->assertCount(4, $json['document']['columns']);
    }

    #[Test]
    public function edit_then_update_a_field_and_reorder(): void
    {
        $document = $this->document();

        $json = $this->postJson(self::URL.'/actions/editField', ['document' => $document, 'index' => 2])->assertOk()->json();
        $this->assertSame(2, $json['editing']);
        $this->assertSame('amount', $json['form']['colsNomeFisico']);

        $form = $json['form'];
        $form['colsNomeLogico'] = 'Total';
        $json = $this->postJson(self::URL.'/actions/updateField', ['document' => $document, 'form' => $form, 'editing' => 2])->assertOk()->json();
        $this->assertSame('Total', $json['document']['columns'][2]['colsNomeLogico']);
        $this->assertSame(-1, $json['editing']);

        $json = $this->postJson(self::URL.'/actions/reorderFields', ['document' => $json['document'], 'order' => [2, 0, 1, 3]])->assertOk()->json();
        $this->assertSame(['amount', 'name', 'status', 'id'], array_column($json['document']['columns'], 'colsNomeFisico'));

        $json = $this->postJson(self::URL.'/actions/moveFieldDown', ['document' => $json['document'], 'index' => 0])->assertOk()->json();
        $this->assertSame(['name', 'amount', 'status', 'id'], array_column($json['document']['columns'], 'colsNomeFisico'));

        $json = $this->postJson(self::URL.'/actions/removeField', ['document' => $json['document'], 'index' => 2])->assertOk()->json();
        $this->assertSame(['name', 'amount', 'id'], array_column($json['document']['columns'], 'colsNomeFisico'));
    }

    #[Test]
    public function a_second_join_on_the_same_table_is_refused_on_the_table_field(): void
    {
        $document = $this->document();
        $form = ['type' => 'left', 'table' => 'suppliers', 'first' => 'items.supplier_id', 'second' => 'suppliers.id', 'selectRaw' => "suppliers.name:supplier_name\nsuppliers.phone"];

        $json = $this->postJson(self::URL.'/actions/addJoin', ['document' => $document, 'form' => $form])->assertOk()->json();
        $this->assertSame([['column' => 'suppliers.name', 'alias' => 'supplier_name'], ['column' => 'suppliers.phone', 'alias' => 'suppliers_phone']], $json['document']['joins'][0]['select']);

        $this->postJson(self::URL.'/actions/addJoin', ['document' => $json['document'], 'form' => $form])
            ->assertStatus(422)->assertJsonValidationErrors(['table']);
        $this->assertNull(session('joinError'));

        // editJoin() rebuilds the editable text.
        $edit = $this->postJson(self::URL.'/actions/editJoin', ['document' => $json['document'], 'index' => 0])->assertOk()->json();
        $this->assertSame("suppliers.name:supplier_name\nsuppliers.phone:suppliers_phone", $edit['form']['selectRaw']);
        $this->assertSame(0, $edit['editing']);
    }

    #[Test]
    public function actions_filters_styles_and_notification_rules(): void
    {
        $document = $this->document();

        $json = $this->postJson(self::URL.'/actions/addAction', ['document' => $document, 'form' => ['colsNomeLogico' => 'Aprovar', 'actionType' => 'livewire', 'actionValue' => 'approve(%id%)', 'actionIcon' => '']])->assertOk()->json();
        $action = end($json['document']['columns']);
        $this->assertSame(['action', 'id', false, 'bx bx-link'], [$action['colsTipo'], $action['colsNomeFisico'], $action['colsGravar'], $action['actionIcon']]);

        $json = $this->postJson(self::URL.'/actions/addCustomFilter', ['document' => $json['document'], 'form' => ['field' => 'supplier_name', 'whereHas' => 'supplier', 'field_relation' => 'name']])->assertOk()->json();
        $json = $this->postJson(self::URL.'/actions/addConditionStyle', ['document' => $json['document'], 'form' => ['field' => 'status', 'condition' => '==', 'value' => 'archived', 'style' => 'color:#999;']])->assertOk()->json();
        $json = $this->postJson(self::URL.'/actions/addNotificationRule', ['document' => $json['document'], 'form' => ['event' => 'created', 'title' => '  Novo item  ', 'audience' => 'staff']])->assertOk()->json();

        $this->assertSame('supplier_name', $json['document']['customFilters'][0]['field']);
        $this->assertSame('status', $json['document']['conditionStyles'][0]['field']);
        $this->assertSame('Novo item', $json['document']['notificationRules'][0]['title']);
        $this->assertSame('info', $json['document']['notificationRules'][0]['type']);

        $json = $this->postJson(self::URL.'/actions/removeCustomFilter', ['document' => $json['document'], 'index' => 0])->assertOk()->json();
        $this->assertSame([], $json['document']['customFilters']);
    }

    #[Test]
    public function an_unknown_action_or_a_bad_argument_is_refused(): void
    {
        $this->postJson(self::URL.'/actions/save', [])->assertNotFound();
        $this->postJson(self::URL.'/actions/loadFromDb', [])->assertNotFound();
        $this->postJson(self::URL.'/actions/editField', [])->assertStatus(422)->assertJsonValidationErrors(['index']);
        $this->postJson(self::URL.'/actions/reorderFields', ['order' => ['x']])->assertStatus(422)->assertJsonValidationErrors(['order.0']);
    }

    // ── Save ────────────────────────────────────────────────────────────────

    #[Test]
    public function save_merges_into_the_stored_config_and_forgets_the_cache(): void
    {
        $service = app(CrudConfigService::class);
        $service->find(CrudConfigApiItem::class, 'items');

        $document = $this->document();
        $document['settings']['displayName'] = 'Produtos';
        $document['settings']['cacheTtl'] = 60;
        $document['columns'][0]['totalizadorEnabled'] = true;
        $document['columns'][0]['totalizadorType'] = 'count';

        $json = $this->putJson(self::URL, ['document' => $document])->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame('ptah:crud-config-updated', $json['event']);
        $this->assertSame('Produtos', $json['document']['settings']['displayName']);

        $stored = $this->stored();
        $this->assertSame('Produtos', $stored['displayName']);
        $this->assertSame(60, $stored['cacheStrategy']['ttl']);
        // colsSelect back to its map, totals to the screen-level section.
        $this->assertSame(['Ativo' => 'active', 'Arquivado' => 'archived'], $stored['cols'][1]['colsSelect']);
        $this->assertSame([['field' => 'name', 'aggregate' => 'count'], ['field' => 'amount', 'aggregate' => 'sum']], $stored['totalizadores']['columns']);
        // Keys the editor has no field for survive (merge, not replacement).
        $this->assertTrue($stored['permissions']['history']);
        $this->assertSame('Produtos', $service->find(CrudConfigApiItem::class, 'items')->config['displayName']);

        // The screen reads it at once.
        $this->assertSame('Produtos', $this->getJson('/ptah/api/crud/items/schema')->json('title'));
        // Nothing flashed for the next Livewire page.
        $this->assertNull(session('crud-success'));
    }

    #[Test]
    public function a_config_the_schema_validator_refuses_is_a_422_and_is_not_written(): void
    {
        $document = $this->document();
        $document['columns'][0]['colsTipo'] = 'bogus';

        $this->putJson(self::URL, ['document' => $document])->assertStatus(422)->assertJsonValidationErrors(['config']);
        $this->assertSame('text', $this->stored()['cols'][0]['colsTipo']);
    }

    #[Test]
    public function forged_document_values_are_refused_by_type(): void
    {
        $this->putJson(self::URL, ['document' => ['settings' => ['cacheTtl' => 'many', 'uiCompactMode' => 'maybe'], 'columns' => ['not-an-object']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document.settings.cacheTtl', 'document.settings.uiCompactMode', 'document.columns.0']);

        $this->putJson(self::URL, [])->assertStatus(422)->assertJsonValidationErrors(['document']);
    }

    #[Test]
    public function the_model_and_route_are_the_screens_whatever_the_client_sends(): void
    {
        $document = $this->document();
        $document['settings']['displayName'] = 'Mine';

        $this->putJson(self::URL, ['document' => $document, 'model' => 'App\\Models\\User', 'configRoute' => 'users', 'route' => 'users'])->assertOk();

        $this->assertSame('Mine', $this->stored()['displayName']);
        $this->assertSame(1, CrudConfig::count());
    }

    // ── Preview and audience ────────────────────────────────────────────────

    #[Test]
    public function the_preview_lists_the_savable_fields_of_the_document(): void
    {
        $document = $this->document();
        $document['columns'][2]['colsFormBlock'] = 'Valores';

        $json = $this->postJson(self::URL.'/preview', ['document' => $document])->assertOk()->json();

        $this->assertSame('Itens', $json['title']);
        $this->assertSame(['name', 'status', 'amount'], array_column($json['fields'], 'field'));
        $this->assertTrue($json['fields'][0]['required']);
        $this->assertSame([['label' => 'Ativo', 'value' => 'active'], ['label' => 'Arquivado', 'value' => 'archived']], $json['fields'][1]['options']);
        $this->assertSame('Valores', $json['fields'][2]['block']);
    }

    #[Test]
    public function the_audience_count_is_the_components(): void
    {
        $this->postJson(self::URL.'/audience-count', ['form' => ['audience' => 'user', 'audienceValue' => '5']])->assertOk()->assertJson(['count' => 1]);
        $this->postJson(self::URL.'/audience-count', ['form' => ['audience' => 'role', 'audienceValue' => '']])->assertOk()->assertJson(['count' => 0]);
    }
}
