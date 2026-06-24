<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class PrintStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers the print screen (crud/print): the printView() snapshot builder, its
 * parity with the listing (same filters/totals via the shared buildBaseQuery)
 * and the CrudPrintController that serves the cached payload.
 */
class CrudPrintTest extends TestCase
{
    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => PrintStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => PrintStub::class,
                'displayName' => 'Itens',
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true, 'colsRenderer' => 'money'],
                ],
                'exportConfig' => ['enabled' => true, 'maxRows' => 5000],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        PrintStub::create(['name' => 'Alpha', 'status' => 'open', 'amount' => 10]);
        PrintStub::create(['name' => 'Bravo', 'status' => 'open', 'amount' => 20]);
        PrintStub::create(['name' => 'Charlie', 'status' => 'done', 'amount' => 5]);
    }

    /** Runs printView() and returns the cached payload for the dispatched token. */
    private function printAndFetch(object $component): array
    {
        $url = null;
        $component->call('printView')->assertDispatched('ptah:open-print', function ($event, $params) use (&$url) {
            $url = $params['url'] ?? null;

            return $url !== null;
        });

        $token = basename(parse_url($url, PHP_URL_PATH));

        return Cache::get('ptah:print:'.$token);
    }

    // ── printView snapshot ───────────────────────────────────────────────────

    #[Test]
    public function print_view_caches_a_payload_and_opens_the_print_window(): void
    {
        $this->makeConfig();
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => PrintStub::class]);
        $payload = $this->printAndFetch($component);

        $this->assertIsArray($payload);
        $this->assertSame('Itens', $payload['title']);
        $this->assertCount(3, $payload['rows']);
        // Columns are the visible, non-action set.
        $this->assertSame(['ID', 'Name', 'Status', 'Amount'], array_column($payload['columns'], 'label'));
        // Cells are pre-rendered HTML (money renderer formats amount).
        $this->assertStringContainsString('R$', implode('', $payload['rows'][0]['cells']));
    }

    #[Test]
    public function print_view_is_a_no_op_when_export_is_disabled(): void
    {
        $this->makeConfig(['exportConfig' => ['enabled' => false]]);
        $this->seedRows();

        Livewire::test(BaseCrud::class, ['model' => PrintStub::class])
            ->call('printView')
            ->assertNotDispatched('ptah:open-print');
    }

    #[Test]
    public function print_snapshot_reflects_the_active_filters(): void
    {
        $this->makeConfig();
        $this->seedRows();

        // Filter name = "Alpha" → only one row must reach the printout.
        $component = Livewire::test(BaseCrud::class, ['model' => PrintStub::class])
            ->set('filters', ['name' => 'Alpha']);

        $payload = $this->printAndFetch($component);

        $this->assertCount(1, $payload['rows']);
        $this->assertSame(1, $payload['totalRecords']);
        $this->assertStringContainsString('Alpha', implode('', $payload['rows'][0]['cells']));
    }

    #[Test]
    public function print_snapshot_includes_totals_respecting_filters(): void
    {
        $this->app->setLocale('pt_BR');
        $this->makeConfig([
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]],
        ]);
        $this->seedRows();

        // Only "open" rows (10 + 20 = 30), not the "done" one.
        $component = Livewire::test(BaseCrud::class, ['model' => PrintStub::class])
            ->set('filters', ['status' => 'open']);

        $payload = $this->printAndFetch($component);

        $amountCol = collect($payload['columns'])->firstWhere('field', 'amount');
        $this->assertNotNull($amountCol['total']);
        // money column → currency formatted total of the FILTERED rows (pt-BR).
        $this->assertStringContainsString('30,00', $amountCol['total']);
        $this->assertStringContainsString('R$', $amountCol['total']);
    }

    // ── Shared-query bug fix: totals respect the global search ────────────────

    #[Test]
    public function totalizadores_now_respect_the_global_search(): void
    {
        $this->makeConfig([
            'totalizadores' => ['enabled' => true, 'columns' => [['field' => 'amount', 'aggregate' => 'sum']]],
        ]);
        $this->seedRows();

        $component = Livewire::test(BaseCrud::class, ['model' => PrintStub::class]);

        // No search → sum of everything (10 + 20 + 5 = 35).
        $this->assertEqualsWithDelta(35, (float) $component->instance()->totalizadoresData()['amount'], 0.001);

        // Search "Alpha" → totals must shrink to the matched row (10), proving the
        // total is built from the SAME filtered query as the listing.
        $component->set('search', 'Alpha');
        $this->assertEqualsWithDelta(10, (float) $component->instance()->totalizadoresData()['amount'], 0.001);
    }

    // ── CrudPrintController ────────────────────────────────────────────────────

    #[Test]
    public function controller_renders_a_cached_snapshot(): void
    {
        $token = 'test-token-render';
        Cache::put('ptah:print:'.$token, [
            'version' => 1,
            'userId' => null,
            'title' => 'Relatório X',
            'columns' => [['label' => 'Name', 'field' => 'name', 'align' => 'text-start', 'total' => null]],
            'rows' => [['cells' => ['<span>Alpha</span>'], 'style' => '']],
            'filters' => [],
            'totalRecords' => 1,
            'truncated' => false,
            'maxRows' => 5000,
            'generatedAt' => '24/06/2026 10:00:00',
        ], now()->addMinutes(10));

        $this->get(route('ptah.print', ['token' => $token]))
            ->assertOk()
            ->assertSee('Relatório X')
            ->assertSee('Alpha');
    }

    #[Test]
    public function controller_returns_404_for_an_unknown_token(): void
    {
        $this->get(route('ptah.print', ['token' => 'does-not-exist']))->assertNotFound();
    }

    #[Test]
    public function controller_forbids_a_snapshot_owned_by_another_user(): void
    {
        $token = 'test-token-owned';
        Cache::put('ptah:print:'.$token, [
            'userId' => 999, // belongs to another user; current request is a guest
            'title' => 'Secret',
            'columns' => [],
            'rows' => [],
            'filters' => [],
        ], now()->addMinutes(10));

        $this->get(route('ptah.print', ['token' => $token]))->assertForbidden();
    }
}
