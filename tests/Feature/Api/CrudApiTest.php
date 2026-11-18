<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\CrudQueryScope;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ApiItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class ApiSmallAmounts implements CrudQueryScope
{
    public function apply(Builder $query, array $params): void
    {
        $query->where('amount', '<=', $params['max']);
    }
}

/**
 * The headless CRUD API (1.59.0): the BaseCrud over JSON. Same engine as the
 * Livewire screen — these tests pin the HTTP contract and that the guards
 * the engine already has hold through it.
 */
class CrudApiTest extends TestCase
{
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

        CrudScreen::register('items', ApiItem::class);
        $this->configure();

        ApiItem::create(['name' => 'Alfa', 'status' => 'active', 'amount' => 10]);
        ApiItem::create(['name' => 'Beta', 'status' => 'archived', 'amount' => 500]);
        ApiItem::create(['name' => 'Gama', 'status' => 'active', 'amount' => 30]);
    }

    private function configure(array $permissions = [], string $route = ''): void
    {
        CrudConfig::updateOrCreate(['model' => ApiItem::class, 'route' => $route], ['config' => [
            'crud' => ApiItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true,
                    'colsSelect' => ['Ativo' => 'active', 'Arquivado' => 'archived'], 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true, 'colsRenderer' => 'money'],
            ],
            'permissions' => $permissions,
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]],
        ]]);
    }

    private function names(array $json): array
    {
        return array_map(fn ($row) => $row['cells']['name']['value'], $json['data']);
    }

    // ── Contract ────────────────────────────────────────────────────────────

    #[Test]
    public function the_schema_describes_the_screen_without_leaking_the_config(): void
    {
        $json = $this->getJson('/ptah/api/crud/items/schema')->assertOk()->json();

        $this->assertSame(['id', 'name', 'status', 'amount'], array_column($json['columns'], 'field'));
        $this->assertSame(['name', 'status', 'amount'], array_column($json['form'], 'field'));
        $this->assertTrue($json['form'][0]['required']);
        $this->assertSame([['label' => 'Ativo', 'value' => 'active'], ['label' => 'Arquivado', 'value' => 'archived']], $json['form'][1]['options']);
        $this->assertSame(['name', 'status'], array_column($json['filters'], 'field'));
        $this->assertTrue($json['permissions']['canCreate']);

        // Named keys only: nothing of the raw config.
        $this->assertArrayNotHasKey('crudConfig', $json);
        $this->assertStringNotContainsString('colsNomeFisico', json_encode($json));
        $this->assertStringNotContainsString(addslashes(ApiItem::class), json_encode($json));
    }

    #[Test]
    public function the_listing_pages_sorts_searches_and_filters(): void
    {
        $json = $this->getJson('/ptah/api/crud/items?sort=name&direction=asc&per_page=2')->assertOk()->json();
        $this->assertSame(['Alfa', 'Beta'], $this->names($json));
        $this->assertSame(['page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], array_intersect_key($json['meta'], array_flip(['page', 'per_page', 'total', 'last_page'])));

        $json = $this->getJson('/ptah/api/crud/items?sort=name&direction=asc&per_page=2&page=2')->json();
        $this->assertSame(['Gama'], $this->names($json));

        // A page past the last one answers the last page.
        $json = $this->getJson('/ptah/api/crud/items?sort=name&direction=asc&per_page=2&page=9')->json();
        $this->assertSame(2, $json['meta']['page']);

        $this->assertSame(['Beta'], $this->names($this->getJson('/ptah/api/crud/items?search=bet')->json()));
        $this->assertSame(['Beta'], $this->names($this->getJson('/ptah/api/crud/items?filters[status]=archived')->json()));
    }

    #[Test]
    public function a_cell_carries_the_raw_value_and_the_rendered_one(): void
    {
        $json = $this->getJson('/ptah/api/crud/items?sort=name&direction=asc')->json();

        $this->assertEquals(10, $json['data'][0]['cells']['amount']['value']);
        $this->assertStringContainsString('10,00', $json['data'][0]['cells']['amount']['html']);
        $this->assertSame('Ativo', $json['data'][0]['cells']['status']['html']);
        $this->assertEquals(540, $json['totals'][0]['value']);
    }

    #[Test]
    public function the_listing_is_stateless(): void
    {
        $this->getJson('/ptah/api/crud/items?search=bet&sort=name&direction=asc')->assertOk();

        // The search of one request does not follow into the next.
        $this->assertCount(3, $this->getJson('/ptah/api/crud/items')->json('data'));
    }

    #[Test]
    public function create_update_delete(): void
    {
        $this->postJson('/ptah/api/crud/items', ['data' => ['status' => 'active']])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['errors' => ['name']]);

        $id = $this->postJson('/ptah/api/crud/items', ['data' => ['name' => 'Delta', 'status' => 'active', 'amount' => 7]])
            ->assertCreated()->assertJsonPath('ok', true)->json('id');
        $this->assertDatabaseHas('items', ['id' => $id, 'name' => 'Delta']);

        $this->getJson("/ptah/api/crud/items/{$id}")->assertOk()->assertJsonPath('data.name', 'Delta');

        // Only the fields sent change.
        $this->putJson("/ptah/api/crud/items/{$id}", ['data' => ['name' => 'Delta 2']])->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseHas('items', ['id' => $id, 'name' => 'Delta 2', 'status' => 'active']);

        $this->deleteJson("/ptah/api/crud/items/{$id}")->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseMissing('items', ['id' => $id]);
    }

    // ── The engine's guards hold through HTTP ───────────────────────────────

    #[Test]
    public function a_screen_that_is_not_registered_does_not_exist(): void
    {
        $this->getJson('/ptah/api/crud/users')->assertNotFound();
        $this->getJson('/ptah/api/crud/'.urlencode(ApiItem::class))->assertNotFound();
    }

    #[Test]
    public function a_forged_select_value_and_a_forged_sort_are_refused(): void
    {
        $this->postJson('/ptah/api/crud/items', ['data' => ['name' => 'X', 'status' => 'hacked']])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['status']]);

        // An unknown sort column falls back instead of reaching ORDER BY.
        $json = $this->getJson('/ptah/api/crud/items?sort=password')->assertOk()->json();
        $this->assertNotSame('password', $json['meta']['sort']);
    }

    #[Test]
    public function the_screen_buttons_gate_the_actions(): void
    {
        $this->configure(['showCreateButton' => false, 'showEditButton' => false, 'showDeleteButton' => false]);
        $id = ApiItem::first()->id;

        $this->postJson('/ptah/api/crud/items', ['data' => ['name' => 'X']])->assertForbidden();
        $this->putJson("/ptah/api/crud/items/{$id}", ['data' => ['name' => 'X']])->assertForbidden();
        $this->deleteJson("/ptah/api/crud/items/{$id}")->assertForbidden();
        $this->assertDatabaseHas('items', ['id' => $id]);

        $this->assertFalse($this->getJson('/ptah/api/crud/items/schema')->json('permissions.canCreate'));
    }

    #[Test]
    public function the_scope_comes_from_the_server_and_covers_every_action(): void
    {
        CrudScreen::register('active-items', ApiItem::class)
            ->lockedFilters(fn () => ['status' => 'active'])
            ->scope(ApiSmallAmounts::class, ['max' => 20]);

        $out = ApiItem::where('name', 'Beta')->first();   // archived, 500
        $big = ApiItem::where('name', 'Gama')->first();   // active, 30 — outside the scope
        $in = ApiItem::where('name', 'Alfa')->first();

        $this->assertSame(['Alfa'], $this->names($this->getJson('/ptah/api/crud/active-items')->json()));

        // No request input widens it: forged scope parameters are ignored…
        $this->assertSame(['Alfa'], $this->names($this->getJson('/ptah/api/crud/active-items?lockedFilters[status]=archived&scope=&scopeParams[max]=9999')->json()));
        // …and a filter can only narrow what the lock already allows.
        $this->assertSame([], $this->names($this->getJson('/ptah/api/crud/active-items?filters[status]=archived')->json()));

        foreach ([$out, $big] as $record) {
            $this->getJson("/ptah/api/crud/active-items/{$record->id}")->assertNotFound();
            $this->putJson("/ptah/api/crud/active-items/{$record->id}", ['data' => ['name' => 'Hacked']])->assertNotFound();
            $this->deleteJson("/ptah/api/crud/active-items/{$record->id}")->assertStatus(422);
            $this->assertDatabaseHas('items', ['id' => $record->id, 'name' => $record->name]);
        }

        $this->getJson("/ptah/api/crud/active-items/{$in->id}")->assertOk();

        // A record created through the screen lands inside its lock.
        $id = $this->postJson('/ptah/api/crud/active-items', ['data' => ['name' => 'Novo', 'status' => 'archived', 'amount' => 5]])->json('id');
        $this->assertSame('active', ApiItem::find($id)->status);
    }

    #[Test]
    public function the_screen_config_is_the_screens_not_the_requests(): void
    {
        $this->configure(['showCreateButton' => false], 'catalog');
        CrudScreen::register('catalog', ApiItem::class);

        $this->assertFalse($this->getJson('/ptah/api/crud/catalog/schema')->json('permissions.canCreate'));
        $this->assertTrue($this->getJson('/ptah/api/crud/items/schema')->json('permissions.canCreate'));
    }

    #[Test]
    public function an_authorize_callback_closes_the_whole_screen(): void
    {
        CrudScreen::register('closed', ApiItem::class)->authorize(fn () => false);

        $this->getJson('/ptah/api/crud/closed')->assertForbidden();
        $this->getJson('/ptah/api/crud/closed/schema')->assertForbidden();
    }
}
