<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class PrefItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * The API keeps what the Livewire screen keeps (1.59.0): reopening where the
 * user left, column choices, named filters — in the same preferences record,
 * so the two screens show the same thing.
 */
class CrudApiPreferencesTest extends TestCase
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

        CrudScreen::register('items', PrefItem::class)->configRoute('');
        CrudConfig::create(['model' => PrefItem::class, 'route' => '', 'config' => [
            'crud' => PrefItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsGravar' => true],
            ],
            'permissions' => [],
        ]]);

        foreach (['Alfa' => 'active', 'Beta' => 'archived', 'Gama' => 'active'] as $name => $status) {
            PrefItem::create(['name' => $name, 'status' => $status, 'amount' => 1]);
        }
    }

    private function names(array $json): array
    {
        return array_map(fn ($row) => $row['cells']['name']['value'], $json['data']);
    }

    // ── Reopening where the user left ───────────────────────────────────────

    #[Test]
    public function remember_saves_and_restore_reopens(): void
    {
        $this->getJson('/ptah/api/crud/items?search=bet&sort=name&direction=asc&filters[status]=archived&remember=1')->assertOk();

        // A plain request still answers only what it was asked.
        $this->assertCount(3, $this->getJson('/ptah/api/crud/items')->json('data'));

        $json = $this->getJson('/ptah/api/crud/items?restore=1')->assertOk()->json();
        $this->assertSame(['Beta'], $this->names($json));
        $this->assertSame('bet', $json['state']['search']);
        $this->assertSame('name', $json['state']['sort']);
        $this->assertSame('ASC', $json['state']['direction']);
        $this->assertSame(['status' => 'archived'], $json['state']['filters']);
    }

    #[Test]
    public function the_page_is_reopened_too(): void
    {
        $this->getJson('/ptah/api/crud/items?sort=name&direction=asc&per_page=1&page=3&remember=1')->assertOk();

        $json = $this->getJson('/ptah/api/crud/items?restore=1')->json();
        $this->assertSame(3, $json['state']['page']);
        $this->assertSame(['Gama'], $this->names($json));
    }

    #[Test]
    public function clearing_is_remembered_as_well(): void
    {
        $this->getJson('/ptah/api/crud/items?search=bet&filters[status]=archived&remember=1')->assertOk();
        $this->getJson('/ptah/api/crud/items?search=&filters=&remember=1')->assertOk();

        $json = $this->getJson('/ptah/api/crud/items?restore=1')->json();
        $this->assertCount(3, $json['data']);
        $this->assertSame('', $json['state']['search']);
    }

    #[Test]
    public function the_livewire_screen_and_the_api_share_the_state(): void
    {
        // Left on the Livewire screen…
        Livewire::test(BaseCrud::class, ['model' => PrefItem::class])->set('search', 'gam');

        // …found through the API.
        $json = $this->getJson('/ptah/api/crud/items?restore=1')->json();
        $this->assertSame(['Gama'], $this->names($json));

        // Left through the API…
        $this->getJson('/ptah/api/crud/items?search=alf&remember=1')->assertOk();

        // …found on the Livewire screen.
        Livewire::test(BaseCrud::class, ['model' => PrefItem::class])->assertSet('search', 'alf');
    }

    // ── Column choices ──────────────────────────────────────────────────────

    #[Test]
    public function column_choices_are_saved_and_shape_the_listing(): void
    {
        $prefs = $this->putJson('/ptah/api/crud/items/preferences', [
            'columns' => ['amount' => false, 'password' => true],
            'column_order' => ['status', 'name', 'not_a_column'],
            'column_widths' => ['name' => 240, 'not_a_column' => 10],
            'view_density' => 'compact',
        ])->assertOk()->json();

        $this->assertFalse($prefs['columns']['amount']);
        $this->assertArrayNotHasKey('password', $prefs['columns']);
        $this->assertSame(['status', 'name'], $prefs['column_order']);
        $this->assertSame(['name' => 240], $prefs['column_widths']);
        $this->assertSame('compact', $prefs['view_density']);

        $schema = $this->getJson('/ptah/api/crud/items/schema')->json();
        $this->assertSame(['status', 'name', 'id'], array_column($schema['columns'], 'field'));
        $this->assertArrayNotHasKey('amount', $this->getJson('/ptah/api/crud/items')->json('data.0.cells'));

        // The same choice on the Livewire screen.
        Livewire::test(BaseCrud::class, ['model' => PrefItem::class])
            ->assertSet('formDataColumns.amount', false)
            ->assertSet('viewDensity', 'compact');
    }

    #[Test]
    public function changing_columns_does_not_lose_the_saved_search(): void
    {
        $this->getJson('/ptah/api/crud/items?search=bet&remember=1')->assertOk();
        $this->putJson('/ptah/api/crud/items/preferences', ['columns' => ['amount' => false]])->assertOk();

        $this->assertSame('bet', $this->getJson('/ptah/api/crud/items?restore=1')->json('state.search'));
    }

    // ── Named filters ───────────────────────────────────────────────────────

    #[Test]
    public function a_filter_is_saved_under_a_name_and_forgotten(): void
    {
        $this->getJson('/ptah/api/crud/items?filters[status]=active&remember=1')->assertOk();

        $prefs = $this->postJson('/ptah/api/crud/items/saved-filters', ['name' => 'Arquivados', 'filters' => ['status' => 'archived']])
            ->assertOk()->json();
        $this->assertSame(['Arquivados' => ['status' => 'archived']], $prefs['saved_filters']);

        // Naming a filter does not replace the one in use.
        $this->assertSame(['status' => 'active'], $this->getJson('/ptah/api/crud/items?restore=1')->json('state.filters'));

        // The Livewire screen lists it.
        Livewire::test(BaseCrud::class, ['model' => PrefItem::class])->assertSet('savedFilters', ['Arquivados' => ['status' => 'archived']]);

        $prefs = $this->deleteJson('/ptah/api/crud/items/saved-filters', ['name' => 'Arquivados'])->assertOk()->json();
        $this->assertSame([], $prefs['saved_filters']);
    }
}
