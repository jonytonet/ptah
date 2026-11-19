<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ExtrasItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id', 'owner_id'];

    protected $hidden = ['owner_id'];
}

/** The detail model: lines of an item, through items.category_id. */
class ExtrasLine extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];
}

/** A detail model with no CRUD config at all. */
class ExtrasOrphan extends Model
{
    protected $table = 'items';
}

/**
 * Form draft, form behaviours, master/detail and column management over the
 * headless API (1.59.0): the contract of each endpoint and that the engine's
 * guards hold through it.
 */
class ExtrasApiTest extends TestCase
{
    private ExtrasItem $alfa;

    private ExtrasItem $beta;

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

        CrudScreen::register('items', ExtrasItem::class)->configRoute('');
        CrudScreen::register('active-items', ExtrasItem::class)->configRoute('')->lockedFilters(fn () => ['status' => 'active']);
        $this->configure();

        $this->setLineConfig();

        $this->alfa = ExtrasItem::create(['name' => 'Alfa', 'status' => 'active', 'amount' => 10]);
        $this->beta = ExtrasItem::create(['name' => 'Beta', 'status' => 'archived', 'amount' => 500]);
        ExtrasLine::create(['name' => 'L1', 'amount' => 1, 'category_id' => $this->alfa->id]);
        ExtrasLine::create(['name' => 'L2', 'amount' => 2, 'category_id' => $this->alfa->id]);
        ExtrasLine::create(['name' => 'L3', 'amount' => 3, 'category_id' => $this->beta->id]);
    }

    /**
     * The detail model's config. `category_id` (the foreign key) is a form
     * field, so a client CAN send another parent — the lock must win.
     *
     * @param  array<string, mixed>  $extra
     */
    private function setLineConfig(array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => ExtrasLine::class, 'route' => ''], ['config' => array_merge([
            'crud' => ExtrasLine::class,
            'displayName' => 'Linhas do item',
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Linha', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Qtd', 'colsTipo' => 'number', 'colsGravar' => true],
                ['colsNomeFisico' => 'category_id', 'colsNomeLogico' => 'Item', 'colsTipo' => 'number', 'colsGravar' => true],
            ],
            'permissions' => [],
        ], $extra)]);
    }

    /** @param  array<string, mixed>  $extra */
    private function configure(array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => ExtrasItem::class, 'route' => ''], ['config' => array_merge([
            'crud' => ExtrasItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true,
                    'colsOnChange' => "merge(data, {'status': value == 'Arquivar' ? 'archived' : 'active', 'owner_id': 7})"],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Ativo' => 'active', 'Arquivado' => 'archived'], 'colsDefaultValue' => 'active'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true],
                ['colsNomeFisico' => 'owner_id', 'colsNomeLogico' => 'Dono', 'colsTipo' => 'number', 'colsGravar' => false],
            ],
            'permissions' => [],
            'formDraft' => ['enabled' => true, 'exclude' => ['amount'], 'ttlDays' => 3],
            'masterDetail' => [
                ['model' => ExtrasLine::class, 'foreignKey' => 'category_id', 'title' => 'Linhas'],
                ['model' => ExtrasOrphan::class, 'foreignKey' => 'category_id'],
                ['title' => 'incomplete entry, skipped'],
            ],
        ], $extra)]);
    }

    // ── Form draft ──────────────────────────────────────────────────────────

    #[Test]
    public function the_draft_context_of_the_create_form(): void
    {
        $json = $this->postJson('/ptah/api/crud/items/form/draft')->assertOk()->json();

        $this->assertStringStartsWith('ptah:draft:'.$json['user'].':', $json['key']);
        $this->assertStringStartsWith($json['prefix'], $json['key']);
        $this->assertStringEndsWith(':new', $json['key']);
        // Excluded by the config, never draftable; the guarded id neither.
        $this->assertSame(['name', 'status'], $json['fields']);
        $this->assertSame(['name' => null, 'status' => 'active'], $json['original']);
        $this->assertSame('', $json['version']);
        $this->assertSame(3, $json['ttlDays']);
        $this->assertFalse($json['restored']);
    }

    #[Test]
    public function the_draft_context_of_an_edit_carries_the_record_fingerprint(): void
    {
        $json = $this->postJson('/ptah/api/crud/items/form/draft', ['id' => $this->alfa->id])->assertOk()->json();

        $this->assertStringEndsWith(':edit:'.$this->alfa->id, $json['key']);
        $this->assertSame(['name' => 'Alfa', 'status' => 'active'], $json['original']);
        $this->assertSame(md5((string) json_encode(['name' => 'Alfa', 'status' => 'active'])), $json['version']);
    }

    #[Test]
    public function a_draft_is_restored_through_the_engine_and_only_its_draftable_fields(): void
    {
        $version = $this->postJson('/ptah/api/crud/items/form/draft', ['id' => $this->alfa->id])->json('version');

        $json = $this->postJson('/ptah/api/crud/items/form/draft', [
            'id' => $this->alfa->id,
            'draft' => ['values' => ['name' => 'Alfa rascunho', 'amount' => 999, 'owner_id' => 1, 'status' => ['x']], 'version' => $version],
        ])->assertOk()->json();

        $this->assertTrue($json['restored']);
        $this->assertFalse($json['discarded']);
        // amount is excluded, owner_id is not a draft field, an array is not a value.
        $this->assertSame(['name' => 'Alfa rascunho', 'status' => 'active'], $json['data']);
        // Nothing is saved by a restore.
        $this->assertDatabaseHas('items', ['id' => $this->alfa->id, 'name' => 'Alfa', 'amount' => 10]);
    }

    #[Test]
    public function a_draft_of_a_record_that_changed_since_is_discarded(): void
    {
        $json = $this->postJson('/ptah/api/crud/items/form/draft', [
            'id' => $this->alfa->id,
            'draft' => ['values' => ['name' => 'Velho'], 'version' => md5('another fingerprint')],
        ])->assertOk()->json();

        $this->assertFalse($json['restored']);
        $this->assertTrue($json['discarded']);
        $this->assertSame([], $json['data']);
    }

    #[Test]
    public function the_draft_endpoint_refuses_what_the_screen_refuses(): void
    {
        // Out of the screen's lock: Beta is archived.
        $this->postJson('/ptah/api/crud/active-items/form/draft', ['id' => $this->beta->id])->assertNotFound();
        $this->postJson('/ptah/api/crud/items/form/draft', ['id' => 99999])->assertNotFound();
        // Forged input.
        $this->postJson('/ptah/api/crud/items/form/draft', ['id' => '1 or 1=1'])->assertStatus(422)->assertJsonValidationErrors('id');
        $this->postJson('/ptah/api/crud/items/form/draft', ['draft' => 'x'])->assertStatus(422)->assertJsonValidationErrors('draft');

        // Editing not allowed: no draft of an edit; creating still has one.
        $this->configure(['permissions' => ['showEditButton' => false]]);
        $this->postJson('/ptah/api/crud/items/form/draft', ['id' => $this->alfa->id])->assertForbidden();
        $this->postJson('/ptah/api/crud/items/form/draft')->assertOk();

        // Feature off.
        $this->configure(['formDraft' => ['enabled' => false]]);
        $this->postJson('/ptah/api/crud/items/form/draft')->assertNotFound();
    }

    // ── Form behaviours ─────────────────────────────────────────────────────

    #[Test]
    public function a_change_runs_the_field_formula_and_answers_only_form_fields(): void
    {
        $json = $this->postJson('/ptah/api/crud/items/form/change', [
            'field' => 'name',
            'data' => ['name' => 'Arquivar', 'status' => 'active'],
        ])->assertOk()->json();

        // status recomputed; owner_id (set by the formula, not a form field) and
        // the unchanged name stay out.
        $this->assertSame(['status' => 'archived'], $json['data']);

        $json = $this->postJson('/ptah/api/crud/items/form/change', [
            'id' => $this->beta->id,
            'field' => 'name',
            'data' => ['name' => 'Beta'],
        ])->assertOk()->json();
        $this->assertSame(['status' => 'active'], $json['data']);
    }

    #[Test]
    public function the_triggers_are_the_form_fields_with_a_formula_or_dependents(): void
    {
        $this->assertSame(['name'], $this->getJson('/ptah/api/crud/items/form/triggers')->assertOk()->json('fields'));

        // A cascade parent is a trigger too; a formula on a non-form column is not.
        $record = CrudConfig::where('model', ExtrasItem::class)->where('route', '')->firstOrFail();
        $config = $record->config;
        $config['cols'][3]['colsSDDependsOn'] = 'status';
        $config['cols'][4]['colsOnChange'] = 'merge(data, {})';
        $record->update(['config' => $config]);

        $this->assertSame(['name', 'status'], $this->getJson('/ptah/api/crud/items/form/triggers')->json('fields'));
        $this->getJson('/ptah/api/crud/nope/form/triggers')->assertNotFound();
    }

    #[Test]
    public function a_change_refuses_forged_input_and_records_out_of_scope(): void
    {
        $this->postJson('/ptah/api/crud/items/form/change', ['field' => 'owner_id', 'data' => []])->assertStatus(422)->assertJsonValidationErrors('field');
        $this->postJson('/ptah/api/crud/items/form/change', ['field' => 'name', 'data' => 'x'])->assertStatus(422)->assertJsonValidationErrors('data');
        $this->postJson('/ptah/api/crud/active-items/form/change', ['id' => $this->beta->id, 'field' => 'name', 'data' => []])->assertNotFound();

        $this->configure(['permissions' => ['showCreateButton' => false]]);
        $this->postJson('/ptah/api/crud/items/form/change', ['field' => 'name', 'data' => []])->assertForbidden();
    }

    // ── Master / detail ─────────────────────────────────────────────────────

    /** The detail screen of a row, as the details endpoint names it. */
    private function child(ExtrasItem $row, int $index = 0, string $screen = 'items'): string
    {
        return "/ptah/api/crud/{$screen}..{$row->id}.{$index}";
    }

    /** @return list<string> */
    private function names(string $url): array
    {
        $rows = $this->getJson($url)->assertOk()->json('data');

        return array_map(fn ($row) => $row['cells']['name']['value'], $rows);
    }

    #[Test]
    public function the_details_of_a_row_are_child_screens_named_by_the_server(): void
    {
        $json = $this->getJson("/ptah/api/crud/items/{$this->alfa->id}/details")->assertOk()->json('data');

        $this->assertSame([
            ['index' => 0, 'title' => 'Linhas', 'screen' => "items..{$this->alfa->id}.0", 'available' => true],
            // No config for that model: unavailable, and its class name never shown.
            ['index' => 1, 'title' => '', 'screen' => "items..{$this->alfa->id}.1", 'available' => false],
        ], $json);
        $this->assertStringNotContainsString('ExtrasOrphan', json_encode($json));
    }

    #[Test]
    public function a_child_screen_lists_only_the_rows_of_its_parent_through_every_endpoint(): void
    {
        $this->assertEqualsCanonicalizing(['L1', 'L2'], $this->names($this->child($this->alfa)));
        $this->assertSame(['L3'], $this->names($this->child($this->beta)));

        // The client's filters narrow inside the lock, never widen it.
        $this->assertSame([], $this->names($this->child($this->alfa).'?search=L3'));

        // The child's own config and columns.
        $schema = $this->getJson($this->child($this->alfa).'/schema')->assertOk()->json();
        $this->assertSame('Linhas do item', $schema['title']);
        $this->assertSame(['id', 'name', 'amount', 'category_id'], array_column($schema['columns'], 'field'));

        // A feature endpoint resolves the same key.
        $this->getJson($this->child($this->alfa).'/columns')->assertOk()->assertJsonPath('data.0.field', 'name');
    }

    #[Test]
    public function a_child_screen_forces_the_parents_key_on_every_write(): void
    {
        // category_id is a form field of the child: the lock still wins.
        $this->postJson($this->child($this->alfa), ['data' => ['name' => 'L9', 'amount' => 9, 'category_id' => $this->beta->id]])->assertCreated();
        $this->assertSame((int) $this->alfa->id, (int) ExtrasLine::query()->where('name', 'L9')->value('category_id'));

        // A line of another parent is not reachable through this child.
        $l3 = ExtrasLine::query()->where('name', 'L3')->firstOrFail();
        $this->getJson($this->child($this->alfa)."/{$l3->id}")->assertNotFound();
        $this->putJson($this->child($this->alfa)."/{$l3->id}", ['data' => ['name' => 'hijacked']])->assertNotFound();
        // The API answers a refused delete with 422 (nothing deleted).
        $this->deleteJson($this->child($this->alfa)."/{$l3->id}")->assertStatus(422);
        $this->assertSame('L3', $l3->fresh()?->name);
    }

    #[Test]
    public function a_child_screen_is_unreachable_through_a_row_or_index_the_parent_does_not_offer(): void
    {
        // The parent row outside the parent's locked filters.
        $this->getJson($this->child($this->beta, 0, 'active-items'))->assertNotFound();
        $this->getJson($this->child($this->alfa, 0, 'active-items'))->assertOk();
        $this->getJson("/ptah/api/crud/active-items/{$this->beta->id}/details")->assertNotFound();

        // Forged indexes and rows.
        $this->getJson($this->child($this->alfa, 7))->assertNotFound();
        $this->getJson($this->child($this->alfa, 2))->assertNotFound(); // incomplete entry
        $this->getJson($this->child($this->alfa, 1))->assertNotFound(); // model without config
        $this->getJson('/ptah/api/crud/items..99999.0')->assertNotFound();
        $this->getJson("/ptah/api/crud/items..0{$this->alfa->id}.0")->assertNotFound();
        $this->getJson("/ptah/api/crud/items..{$this->alfa->id}.00")->assertNotFound();
        $this->getJson("/ptah/api/crud/nope..{$this->alfa->id}.0")->assertNotFound();
        $this->getJson('/ptah/api/crud/items/99999/details')->assertNotFound();

        // No master/detail on the parent at all.
        $this->configure(['masterDetail' => null]);
        $this->getJson($this->child($this->alfa))->assertNotFound();
        $this->getJson("/ptah/api/crud/items/{$this->alfa->id}/details")->assertNotFound();
    }

    #[Test]
    public function a_child_screen_has_its_own_permissions_and_its_parents_gate(): void
    {
        $this->setLineConfig(['permissions' => ['showCreateButton' => false]]);
        $this->postJson($this->child($this->alfa), ['data' => ['name' => 'L9']])->assertForbidden();
        $this->getJson($this->child($this->alfa))->assertOk();
        $this->assertFalse($this->getJson($this->child($this->alfa).'/schema')->json('permissions.canCreate'));
        $this->assertSame(0, ExtrasLine::query()->where('name', 'L9')->count());

        // The parent's own gate closes its children.
        CrudScreen::register('closed-items', ExtrasItem::class)->configRoute('')->authorize(fn () => false);
        $this->getJson($this->child($this->alfa, 0, 'closed-items'))->assertForbidden();
    }

    #[Test]
    public function a_registered_key_may_not_look_like_a_child_screen(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CrudScreen::register("items..{$this->alfa->id}.0", ExtrasLine::class);
    }

    // ── Row link and responsive cards ───────────────────────────────────────

    #[Test]
    public function each_row_carries_its_safe_link(): void
    {
        $this->assertNull($this->getJson('/ptah/api/crud/items')->assertOk()->json('data.0.link'));

        $this->configure(['configLinkLinha' => '/items/%id%/view']);
        $rows = $this->getJson('/ptah/api/crud/items?sort=id&direction=ASC')->assertOk()->json('data');
        $this->assertSame("/items/{$this->alfa->id}/view", $rows[0]['link']);

        // A scheme that could run something: no link at all.
        $this->configure(['configLinkLinha' => "java\tscript:alert(%id%)"]);
        $this->assertNull($this->getJson('/ptah/api/crud/items')->assertOk()->json('data.0.link'));
    }

    #[Test]
    public function the_responsive_cards_switch_reaches_the_client(): void
    {
        $this->assertTrue($this->getJson('/ptah/api/crud/items/schema')->assertOk()->json('features.responsiveCards'));

        config(['ptah.crud.responsive_cards' => false]);
        $this->assertFalse($this->getJson('/ptah/api/crud/items/schema')->assertOk()->json('features.responsiveCards'));
    }

    // ── Columns ─────────────────────────────────────────────────────────────

    #[Test]
    public function the_columns_menu_lists_every_column_but_hidden_attributes_in_order(): void
    {
        $this->putJson('/ptah/api/crud/items/preferences', ['columns' => ['status' => false], 'column_order' => ['amount', 'name']])->assertOk();

        $json = $this->getJson('/ptah/api/crud/items/columns')->assertOk()->json('data');

        // owner_id is $hidden on the model; id is never offered (as in the Livewire menu).
        $this->assertSame([
            ['field' => 'amount', 'label' => 'Valor', 'visible' => true],
            ['field' => 'name', 'label' => 'Nome', 'visible' => true],
            ['field' => 'status', 'label' => 'Situação', 'visible' => false],
        ], $json);
    }

    #[Test]
    public function reset_brings_back_the_default_layout_or_one_width(): void
    {
        $this->putJson('/ptah/api/crud/items/preferences', [
            'columns' => ['status' => false, 'name' => false],
            'column_order' => ['amount', 'name'],
            'column_widths' => ['name' => 200, 'amount' => 120],
        ])->assertOk();

        // One width (the double-click on the resize edge).
        $json = $this->postJson('/ptah/api/crud/items/columns/reset', ['field' => 'name'])->assertOk()->json('preferences');
        $this->assertSame(['amount' => 120], $json['column_widths']);
        $this->assertSame(['amount', 'name'], $json['column_order']);

        // Everything.
        $json = $this->postJson('/ptah/api/crud/items/columns/reset')->assertOk()->json();
        $this->assertSame([], $json['preferences']['column_order']);
        $this->assertSame([], $json['preferences']['column_widths']);
        $this->assertNotContains(false, $json['preferences']['columns']);
        $this->assertSame(['name', 'status', 'amount'], array_column($json['data'], 'field'));

        // Kept: the next reading sees it.
        $this->assertNotContains(false, $this->getJson('/ptah/api/crud/items/preferences')->json('columns'));
    }

    #[Test]
    public function reset_refuses_a_column_the_screen_does_not_offer(): void
    {
        $this->postJson('/ptah/api/crud/items/columns/reset', ['field' => 'owner_id'])->assertStatus(422)->assertJsonValidationErrors('field');
        $this->postJson('/ptah/api/crud/items/columns/reset', ['field' => ['name']])->assertStatus(422);
        $this->getJson('/ptah/api/crud/nope/columns')->assertNotFound();
    }
}
