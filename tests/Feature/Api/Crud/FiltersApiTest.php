<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\Concerns\FiltersRuntime;
use Ptah\Crud\CrudScreen;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class FltItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id', 'owner_id', 'is_active', 'created_at'];

    /** A `$hidden` attribute is never a filter, even when the config says so. */
    protected $hidden = ['is_active'];
}

/**
 * The filter panel of the React and Vue stacks (1.59.0): the endpoints of
 * FiltersApiController and the FiltersRuntime trait the listing request
 * runs. Same engine as the Livewire panel — these pin the contract and that
 * the engine's own checks of fields, operators and scope hold through it.
 */
class FiltersApiTest extends TestCase
{
    private const BASE = '/ptah/api/crud';

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

        CrudScreen::register('items', FltItem::class)->configRoute('');
        CrudConfig::updateOrCreate(['model' => FltItem::class, 'route' => ''], ['config' => [
            'crud' => FltItem::class,
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsIsFilterable' => true,
                    'colsSelect' => ['Ativo' => 'active', 'Arquivado' => 'archived']],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Valor', 'colsTipo' => 'number', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'category_id', 'colsNomeLogico' => 'Categoria', 'colsTipo' => 'searchdropdown', 'colsIsFilterable' => 'S',
                    'colsSDModel' => FltItem::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model', 'colsSDStartList' => 'top'],
                ['colsNomeFisico' => 'owner_id', 'colsNomeLogico' => 'Dono', 'colsTipo' => 'searchdropdown', 'colsIsFilterable' => 'S',
                    'colsSDModel' => FltItem::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model',
                    'colsSDDependsOn' => 'category_id', 'colsSDFilterColumn' => 'category_id', 'colsSDPlaceholder' => 'Escolha o dono'],
                ['colsNomeFisico' => 'created_at', 'colsNomeLogico' => 'Criado em', 'colsTipo' => 'date', 'colsIsFilterable' => 'S'],
                ['colsNomeFisico' => 'is_active', 'colsNomeLogico' => 'Ativo', 'colsTipo' => 'boolean', 'colsIsFilterable' => 'S'],
                ['colsTipo' => 'action', 'colsNomeLogico' => 'Abrir', 'actionType' => 'link', 'actionValue' => '/x/%id%', 'colsIsFilterable' => 'S'],
            ],
            'customFilters' => [
                ['field' => 'region', 'label' => 'Região', 'colsFilterType' => 'searchdropdown'],
                ['field' => 'is_active', 'label' => 'Ativo (custom)', 'colsFilterType' => 'text'],
            ],
            'permissions' => [],
        ]]);

        FltItem::create(['name' => 'Alfa', 'status' => 'active', 'amount' => 10, 'category_id' => 1, 'owner_id' => 10, 'created_at' => '2026-01-05 10:00:00']);
        FltItem::create(['name' => 'Beta', 'status' => 'archived', 'amount' => 500, 'category_id' => 2, 'owner_id' => 20, 'created_at' => '2026-02-07 10:00:00']);
        FltItem::create(['name' => 'Gama', 'status' => 'active', 'amount' => 30, 'category_id' => 1, 'owner_id' => null, 'created_at' => '2026-03-09 10:00:00']);
    }

    private function idOf(string $name): int
    {
        return (int) FltItem::where('name', $name)->value('id');
    }

    /**
     * The FiltersRuntime trait over the engine, outside CrudRuntime (which is
     * final): the same boot/mount CrudRuntime::for() runs, page handling as
     * there.
     */
    private function engine(string $screen = 'items'): BaseCrud
    {
        $engine = new class extends BaseCrud
        {
            use FiltersRuntime;

            public function getPage($pageName = 'page')
            {
                return 1;
            }

            public function setPage($page, $pageName = 'page') {}

            public function resetPage($pageName = 'page') {}

            /** @param  array<string, mixed>  $input */
            public function applyForTest(array $input): void
            {
                $this->applyFiltersState($input);
            }

            /** @return array<string, mixed> */
            public function stateForTest(): array
            {
                return $this->filtersState();
            }

            /** @return list<string> */
            public function namesForTest(): array
            {
                return $this->rows()->getCollection()->pluck('name')->sort()->values()->all();
            }
        };

        app()->call([$engine, 'boot']);
        $engine->mount(...app(CrudScreenRegistry::class)->find($screen)->mountArguments(request()));

        return $engine;
    }

    // ── meta ────────────────────────────────────────────────────────────────

    #[Test]
    public function meta_describes_each_filter_as_the_panel_draws_it(): void
    {
        $json = $this->getJson(self::BASE.'/items/filters/meta')->assertOk()->json();
        $byField = array_column($json['filters'], null, 'field');

        // Filterable columns in config order, then the custom filters; never
        // an action column nor a `$hidden` attribute (neither as a custom filter).
        $this->assertSame(['name', 'status', 'amount', 'category_id', 'owner_id', 'created_at', 'region'], array_column($json['filters'], 'field'));

        $this->assertSame('text', $byField['name']['kind']);
        $this->assertSame(['LIKE', '=', '!=', 'LIKE_START', 'LIKE_END', 'IS NULL', 'IS NOT NULL'], $byField['name']['operators']);
        $this->assertSame('number', $byField['amount']['kind']);
        $this->assertContains('>=', $byField['amount']['operators']);
        $this->assertSame('select', $byField['status']['kind']);
        $this->assertSame([['label' => 'Ativo', 'value' => 'active'], ['label' => 'Arquivado', 'value' => 'archived']], $byField['status']['options']);
        $this->assertSame('date', $byField['created_at']['kind']);
        $this->assertSame(['start' => ['>=', '>', '='], 'end' => ['<=', '<', '=']], $byField['created_at']['date_operators']);

        $this->assertSame('searchdropdown', $byField['owner_id']['kind']);
        $this->assertSame(['=', '!=', 'IS NULL', 'IS NOT NULL'], $byField['owner_id']['operators']);
        $this->assertSame('category_id', $byField['owner_id']['depends_on']);
        $this->assertSame('Escolha o dono', $byField['owner_id']['placeholder']);
        $this->assertNull($byField['category_id']['depends_on']);
        $this->assertSame('top', $byField['category_id']['start_list']);

        // A custom searchdropdown is the text filter since 1.41.5.
        $this->assertTrue($byField['region']['custom']);
        $this->assertSame('text', $byField['region']['kind']);

        $this->assertTrue($json['quick_dates']['enabled']);
        $this->assertSame('created_at', $json['quick_dates']['column']);
        $this->assertSame(['today', 'yesterday', 'last7', 'last30', 'week', 'month', 'lastMonth', 'quarter', 'year'], $json['quick_dates']['periods']);
        $this->assertTrue($json['advanced']['enabled']);
        $this->assertSame(['name', 'status', 'amount', 'category_id', 'owner_id', 'created_at'], array_column($json['advanced']['fields'], 'field'));
        $this->assertSame(['name', 'status', 'amount', 'category_id', 'owner_id', 'created_at'], $json['cell_fields']);
        $this->assertSame(['empty' => '__ptah_empty__', 'filled' => '__ptah_filled__'], $json['sentinels']);

        // Named keys only: nothing of the raw config.
        $raw = json_encode($json);
        $this->assertStringNotContainsString('colsNomeFisico', $raw);
        $this->assertStringNotContainsString('colsSDModel', $raw);
        $this->assertStringNotContainsString(addslashes(FltItem::class), $raw);
        $this->assertStringNotContainsString('is_active', $raw);
    }

    #[Test]
    public function quick_dates_are_off_on_a_screen_without_a_date_filter(): void
    {
        $config = CrudConfig::where('model', FltItem::class)->where('route', '')->firstOrFail();
        $cfg = $config->config;
        $cfg['cols'] = array_values(array_filter($cfg['cols'], fn ($c) => ($c['colsNomeFisico'] ?? '') !== 'created_at'));
        $config->update(['config' => $cfg]);

        $this->assertFalse($this->getJson(self::BASE.'/items/filters/meta')->json('quick_dates.enabled'));
    }

    #[Test]
    public function the_screen_guards_close_every_filter_endpoint(): void
    {
        CrudScreen::register('closed', FltItem::class)->configRoute('')->authorize(fn () => false);

        $this->getJson(self::BASE.'/nope/filters/meta')->assertNotFound();
        $this->getJson(self::BASE.'/closed/filters/meta')->assertForbidden();
        $this->getJson(self::BASE.'/closed/filters/url?f[status]=active')->assertForbidden();
        $this->postJson(self::BASE.'/closed/filters/by-cell', ['row' => $this->idOf('Alfa'), 'field' => 'status'])->assertForbidden();
        $this->putJson(self::BASE.'/closed/filters/labels', ['labels' => ['category_id' => 'Alfa']])->assertForbidden();
    }

    // ── url filters ─────────────────────────────────────────────────────────

    #[Test]
    public function the_link_filters_are_answered_as_the_engine_accepted_them(): void
    {
        $data = $this->getJson(self::BASE.'/items/filters/url?f[status]=archived&f[password]=x&f[is_active]=1&f[name][op]=EVIL&f[name][val]=Al&f[amount][]=10&f[amount][]=30')
            ->assertOk()->json('data');

        $this->assertSame([
            ['field' => 'status', 'label' => 'Situação', 'op' => '=', 'value' => 'archived'],
            // A forged operator falls back to "=".
            ['field' => 'name', 'label' => 'Nome', 'op' => '=', 'value' => 'Al'],
            ['field' => 'amount', 'label' => 'Valor', 'op' => 'IN', 'value' => '10, 30'],
        ], $data);

        $this->assertSame([], $this->getJson(self::BASE.'/items/filters/url')->json('data'));
    }

    // ── filter by cell ──────────────────────────────────────────────────────

    #[Test]
    public function filter_by_cell_reads_the_value_from_the_record(): void
    {
        $beta = $this->idOf('Beta');

        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $beta, 'field' => 'status', 'state' => ['filters' => ['name' => 'be']]])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state.filters.status', 'archived')
            ->assertJsonPath('state.filters.name', 'be')
            ->assertJsonPath('state.operators.status', '=');

        // A date filters that day, as a from/to range.
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $beta, 'field' => 'created_at'])
            ->assertOk()
            ->assertJsonPath('state.date_ranges.created_at_start', '2026-02-07')
            ->assertJsonPath('state.date_ranges.created_at_end', '2026-02-07');

        // No value: filters the empty ones.
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $this->idOf('Gama'), 'field' => 'owner_id'])
            ->assertOk()
            ->assertJsonPath('state.operators.owner_id', 'IS NULL');
    }

    #[Test]
    public function filter_by_cell_refuses_what_the_engine_refuses(): void
    {
        $beta = $this->idOf('Beta');

        // Not a filterable column, a `$hidden` one, an unknown one.
        foreach (['id', 'is_active', 'password'] as $field) {
            $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $beta, 'field' => $field])
                ->assertStatus(422)->assertJsonPath('ok', false);
        }

        // A row outside the current listing (its other filters still apply).
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $beta, 'field' => 'status', 'state' => ['filters' => ['name' => 'alf']]])
            ->assertStatus(422);
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => 99999, 'field' => 'status'])->assertStatus(422);

        // Malformed requests.
        $this->postJson(self::BASE.'/items/filters/by-cell', ['field' => 'status'])->assertStatus(422);
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => ['x'], 'field' => 'status'])->assertStatus(422);
        $this->postJson(self::BASE.'/items/filters/by-cell', ['row' => $beta, 'field' => ['status']])->assertStatus(422);
    }

    #[Test]
    public function filter_by_cell_never_escapes_the_screens_lock(): void
    {
        CrudScreen::register('active-items', FltItem::class)->configRoute('')->lockedFilters(fn () => ['status' => 'active']);

        $this->postJson(self::BASE.'/active-items/filters/by-cell', ['row' => $this->idOf('Beta'), 'field' => 'name'])->assertStatus(422);
        $this->postJson(self::BASE.'/active-items/filters/by-cell', ['row' => $this->idOf('Alfa'), 'field' => 'name'])
            ->assertOk()->assertJsonPath('state.filters.name', 'Alfa');
    }

    // ── more than one value (1.63.0) ────────────────────────────────────────

    private function makeMultiple(string ...$fields): void
    {
        $config = CrudConfig::where('model', FltItem::class)->first();
        $data = $config->config;
        foreach ($data['cols'] as $i => $col) {
            if (in_array($col['colsNomeFisico'] ?? '', $fields, true)) {
                $data['cols'][$i]['colsFilterMultiple'] = true;
            }
        }
        $config->update(['config' => $data]);
    }

    #[Test]
    public function the_meta_flags_the_filters_that_take_more_than_one_value(): void
    {
        $this->makeMultiple('status', 'category_id', 'amount');
        $byField = collect($this->getJson(self::BASE.'/items/filters/meta')->assertOk()->json('filters'))->keyBy('field');

        $this->assertTrue($byField['status']['multiple']);
        $this->assertTrue($byField['category_id']['multiple']);
        // Only a select or a searchdropdown can be multiple.
        $this->assertFalse($byField['amount']['multiple']);
        $this->assertFalse($byField['owner_id']['multiple']);
        $this->assertFalse($byField['region']['multiple']);
    }

    #[Test]
    public function a_multiple_filter_lists_by_in_and_keeps_a_label_per_value(): void
    {
        $this->makeMultiple('status', 'category_id');

        $engine = $this->engine();
        $engine->filters = ['status' => ['archived', 'active']];
        $this->assertSame(['Alfa', 'Beta', 'Gama'], $engine->namesForTest());
        $engine->filters = ['category_id' => ['2', '9']];
        $this->assertSame(['Beta'], $engine->namesForTest());
        $engine->filterOperators = ['category_id' => '!='];
        $this->assertSame(['Alfa', 'Gama'], $engine->namesForTest());

        // A filter of one value keeps one text, not a map.
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['owner_id' => ['10' => 'Dez']]])->assertStatus(422);

        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['category_id' => ['1' => 'Um', '2' => 'Dois']]])
            ->assertOk()
            ->assertJsonPath('labels.category_id', ['1' => 'Um', '2' => 'Dois']);
        $this->assertSame(['1' => 'Um', '2' => 'Dois'], $this->getJson(self::BASE.'/items/filters/meta')->json('labels.category_id'));

        // An empty map forgets it; an oversized one is refused.
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['category_id' => []]])->assertOk();
        $this->assertNull($this->getJson(self::BASE.'/items/filters/meta')->json('labels.category_id'));
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['category_id' => array_fill_keys(range(1, BaseCrud::FILTER_LIST_MAX + 1), 'x')]])
            ->assertStatus(422);
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['category_id' => ['1' => ['nested']]]])->assertStatus(422);
    }

    // ── labels of searchdropdown filters ────────────────────────────────────

    #[Test]
    public function the_label_of_a_searchdropdown_filter_is_kept_for_the_next_opening(): void
    {
        $this->putJson(self::BASE.'/items/filters/labels', [
            'labels' => ['category_id' => 'Alfa'],
            'state' => ['filters' => ['category_id' => $this->idOf('Alfa')]],
        ])->assertOk()->assertJsonPath('labels.category_id', 'Alfa');

        $this->assertSame(['category_id' => 'Alfa'], $this->getJson(self::BASE.'/items/filters/meta')->json('labels'));

        // The state sent with it was saved with it: a restored listing has it.
        $this->assertEquals($this->idOf('Alfa'), $this->getJson(self::BASE.'/items?restore=1')->json('state.filters.category_id'));

        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['owner_id' => 'Gama']])->assertOk();
        $this->assertSame(['category_id' => 'Alfa', 'owner_id' => 'Gama'], $this->getJson(self::BASE.'/items/filters/meta')->json('labels'));

        // null forgets one; replace sets the whole map (the panel was cleared).
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => ['category_id' => null]])->assertOk();
        $this->assertSame(['owner_id' => 'Gama'], $this->getJson(self::BASE.'/items/filters/meta')->json('labels'));
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => [], 'replace' => true])->assertOk();
        $this->assertSame([], $this->getJson(self::BASE.'/items/filters/meta')->json('labels'));
    }

    #[Test]
    public function a_label_is_only_kept_for_a_searchdropdown_filter_of_the_screen(): void
    {
        foreach ([['name' => 'x'], ['is_active' => 'x'], ['region' => 'x'], ['category_id' => ['nested']], ['category_id' => str_repeat('a', 256)]] as $labels) {
            $this->putJson(self::BASE.'/items/filters/labels', ['labels' => $labels])->assertStatus(422)->assertJsonPath('ok', false);
        }
        $this->putJson(self::BASE.'/items/filters/labels', ['labels' => 'x'])->assertStatus(422);

        $this->assertSame([], $this->getJson(self::BASE.'/items/filters/meta')->json('labels'));
    }

    // ── what the listing already takes (the panel builds on it) ─────────────

    #[Test]
    public function the_listing_takes_operators_date_ranges_and_quick_dates(): void
    {
        $names = fn (string $query) => array_map(fn ($row) => $row['cells']['name']['value'], $this->getJson(self::BASE.'/items?sort=name&direction=asc&'.$query)->json('data'));

        $this->assertSame(['Beta', 'Gama'], $names('filters[amount]=30&operators[amount]=>='));
        $this->assertSame(['Gama'], $names('operators[owner_id]=IS NULL'));
        $this->assertSame(['Alfa'], $names('filters[name]=al&operators[name]=LIKE_START'));
        $this->assertSame(['Beta', 'Gama'], $names('date_ranges[created_at_start]=2026-02-07&date_operators[created_at_start]=>='));
        $this->assertSame(['Gama'], $names('date_ranges[created_at_start]=2026-02-07&date_operators[created_at_start]=>'));
        $this->assertSame(['Alfa', 'Gama'], $names('filters[status]=__ptah_filled__&filters[category_id]=1'));
        $this->assertSame([], $names('quick_date=today'));

        // A forged operator is not an injection and not a 500.
        $this->getJson(self::BASE.'/items?filters[name]=al&operators[name]='.urlencode('= 1 OR 1=1 --'))->assertOk();
        // A range over a column that is not a filter does not filter by it.
        $this->assertSame(['Alfa', 'Beta', 'Gama'], $names('date_ranges[password_start]=2000-01-01'));
    }

    #[Test]
    public function a_searchdropdown_filter_cascades_on_its_parent_filter(): void
    {
        // The child is closed until the parent filter has a value…
        $this->assertSame([], $this->postJson(self::BASE.'/items/lookup/owner_id', ['q' => '', 'filter' => true, 'context' => []])->json('data'));

        // …then lists only what belongs to the parent value.
        $found = $this->postJson(self::BASE.'/items/lookup/owner_id', ['q' => '', 'filter' => true, 'context' => ['category_id' => 1]])->json('data');
        $this->assertSame(['Alfa', 'Gama'], collect($found)->pluck('label')->sort()->values()->all());
    }

    // ── FiltersRuntime: what travels with the listing request ──────────────

    #[Test]
    public function advanced_search_rows_filter_with_and_or_logic(): void
    {
        $engine = $this->engine();
        $engine->applyForTest(['advanced' => [
            ['field' => 'name', 'operator' => 'LIKE', 'value' => 'alf', 'logic' => 'AND'],
        ]]);
        $this->assertSame(['Alfa'], $engine->namesForTest());
        $this->assertSame([['field' => 'name', 'operator' => 'LIKE', 'value' => 'alf', 'logic' => 'AND']], $engine->stateForTest()['advanced']);

        $engine = $this->engine();
        $engine->applyForTest(['advanced' => [
            ['field' => 'name', 'operator' => '=', 'value' => 'Alfa', 'logic' => 'OR'],
            ['field' => 'amount', 'operator' => '>', 'value' => '100', 'logic' => 'or'],
        ]]);
        $this->assertSame(['Alfa', 'Beta'], $engine->namesForTest());

        // An emptied list turns the advanced search off.
        $engine->applyForTest(['advanced' => '']);
        $this->assertFalse($engine->advancedSearchActive);
        $this->assertSame(['Alfa', 'Beta', 'Gama'], $engine->namesForTest());
    }

    #[Test]
    public function a_forged_advanced_row_is_dropped(): void
    {
        $engine = $this->engine();
        $engine->applyForTest(['advanced' => [
            // Not a field of the screen, a `$hidden` one: the oracle the engine closes.
            ['field' => 'password', 'operator' => '=', 'value' => 'x'],
            ['field' => 'is_active', 'operator' => '=', 'value' => '0'],
            // An operator no strategy implements, an injection attempt.
            ['field' => 'name', 'operator' => 'SOUNDS LIKE', 'value' => 'x'],
            ['field' => 'name', 'operator' => '= 1 OR 1=1 --', 'value' => 'x'],
            // Values that are not one value, a field that is not a string.
            ['field' => 'name', 'operator' => '=', 'value' => ['Alfa']],
            ['field' => 'name', 'operator' => '=', 'value' => '  '],
            ['field' => ['name'], 'operator' => '=', 'value' => 'Alfa'],
            'not a row',
        ]]);

        $this->assertSame([], $engine->advancedSearchFields);
        $this->assertFalse($engine->advancedSearchActive);
        $this->assertSame(['Alfa', 'Beta', 'Gama'], $engine->namesForTest());

        // More rows than a person builds are cut.
        $engine->applyForTest(['advanced' => array_fill(0, 50, ['field' => 'name', 'operator' => 'LIKE', 'value' => 'a'])]);
        $this->assertCount(20, $engine->advancedSearchFields);
    }

    #[Test]
    public function the_engine_still_refuses_a_row_that_reaches_it_unchecked(): void
    {
        // Even past the trait, buildActiveFilters() drops a field outside the
        // screen — the trait's own check is not the only gate.
        $engine = $this->engine();
        $engine->advancedSearchActive = true;
        $engine->advancedSearchFields = [['field' => 'is_active', 'operator' => '=', 'value' => '0', 'logic' => 'AND']];

        $this->assertSame(['Alfa', 'Beta', 'Gama'], $engine->namesForTest());
    }

    #[Test]
    public function link_filters_travel_with_the_state_and_survive_the_panel_values(): void
    {
        $engine = $this->engine();
        // What applyStateNow() does first: the panel's filters (which discard
        // the link filters, as on Livewire)…
        $engine->filters = ['name' => 'a'];
        $engine->updatedFilters();
        // …then the request's own `f`, through captureUrlFilters().
        $engine->applyForTest(['f' => ['status' => 'archived', 'password' => 'x', 'name' => ['op' => 'DROP', 'val' => 'Beta']]]);

        $this->assertSame(['status' => ['op' => '=', 'val' => 'archived'], 'name' => ['op' => '=', 'val' => 'Beta']], $engine->urlFilters);
        $this->assertSame(['Beta'], $engine->namesForTest());
        $this->assertEquals((object) $engine->urlFilters, $engine->stateForTest()['f']);

        // The query string is left as it was.
        $this->assertFalse(request()->query->has('f'));

        // Sent empty: no link filters.
        $engine->applyForTest(['f' => '']);
        $this->assertSame([], $engine->urlFilters);
    }

    #[Test]
    public function the_search_is_recorded_in_the_history(): void
    {
        $engine = $this->engine();
        $engine->search = 'alf';
        $engine->applyForTest(['search' => 'alf']);
        $engine->search = 'bet';
        $engine->applyForTest(['search' => 'bet']);
        $engine->search = 'alf';
        $engine->applyForTest(['search' => 'alf']);

        // Most recent first, no repeats.
        $this->assertSame(['alf', 'bet'], $engine->searchHistory);

        // A request that does not search records nothing.
        $engine->search = 'gam';
        $engine->applyForTest([]);
        $this->assertSame(['alf', 'bet'], $engine->searchHistory);
    }
}
