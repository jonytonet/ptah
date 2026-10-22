<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Services\Crud\CrudConfigService;
use Ptah\Tests\TestCase;

/**
 * 1.50.1: find() queried by model/route, then asked the cache for the id it
 * had just found and queried again by that id — two queries on every screen,
 * the cache saving nothing. Found while re-running the landing benchmark.
 */
class CrudConfigFindQueryCountTest extends TestCase
{
    private function queriesFor(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    #[Test]
    public function a_cache_miss_and_a_cache_hit_are_one_query_each(): void
    {
        CrudConfig::create(['model' => 'QcItem', 'route' => '', 'config' => ['cols' => []]]);
        $service = app(CrudConfigService::class);

        $this->assertSame(1, $this->queriesFor(fn () => $service->find('QcItem')), 'Miss: uma query.');
        $this->assertSame(1, $this->queriesFor(fn () => $service->find('QcItem')), 'Hit: uma query, pela chave.');
        $this->assertSame('QcItem', $service->find('QcItem')?->model);
    }

    #[Test]
    public function a_deleted_row_behind_a_cached_id_falls_back_to_the_lookup(): void
    {
        $row = CrudConfig::create(['model' => 'QcItem', 'route' => '', 'config' => ['cols' => []]]);
        $service = app(CrudConfigService::class);
        $service->find('QcItem');

        $row->delete();
        $this->assertNull($service->find('QcItem'));

        CrudConfig::create(['model' => 'QcItem', 'route' => '', 'config' => ['cols' => [['colsNomeFisico' => 'x']]]]);
        $this->assertSame('x', $service->find('QcItem')?->config['cols'][0]['colsNomeFisico'] ?? null);
    }

    #[Test]
    public function a_route_config_created_later_is_not_hidden_by_the_global_cache(): void
    {
        CrudConfig::create(['model' => 'QcItem', 'route' => '', 'config' => ['cols' => []]]);
        $service = app(CrudConfigService::class);
        $this->assertSame('', $service->find('QcItem', 'qc/screen')?->route);

        CrudConfig::create(['model' => 'QcItem', 'route' => 'qc/screen', 'config' => ['cols' => []]]);
        $this->assertSame('qc/screen', $service->find('QcItem', 'qc/screen')?->route);
    }
}
