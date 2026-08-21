<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Http\Controllers\ExportController;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use ReflectionMethod;

class TotalizerStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Guards ExportController::getTotalizers() against SQL injection through the
 * `field` name configured in crud_configs.config.totalizadores.columns — it
 * is interpolated directly into sum()/avg()/count()/max()/min() (an
 * identifier position raw queries cannot bind), the same shape of hole
 * HasCrudQuery::totalizers() already closes on the listing screen with
 * SqlIdentifier::isSafe(). A malicious field must be skipped silently
 * (same semantics as the screen), while a legitimate field must keep working.
 */
class ExportControllerTotalizersSqlSafetyTest extends TestCase
{
    private function configureStub(array $columns): void
    {
        CrudConfig::create([
            'model' => 'TotalizerStub',
            'route' => '',
            'config' => [
                'crud' => TotalizerStub::class,
                'totalizadores' => [
                    'enabled' => true,
                    'columns' => $columns,
                ],
                'ui' => ['showTotalizador' => true],
                'permissions' => [],
            ],
        ]);
    }

    private function callGetTotalizers(): array
    {
        $controller = new ExportController;
        $method = new ReflectionMethod($controller, 'getTotalizers');

        return $method->invoke($controller, TotalizerStub::query(), 'TotalizerStub');
    }

    #[Test]
    public function the_config_is_resolved_by_the_full_identifier_not_the_class_basename(): void
    {
        // Real projects store the canonical key or the FQCN in crud_configs.model
        // — never the class_basename. The lookup must find this row when handed
        // the full class name (exact match), which class_basename('...\TotalizerStub')
        // alone would miss for any namespaced/nested identifier.
        CrudConfig::create([
            'model' => TotalizerStub::class,
            'route' => '',
            'config' => [
                'crud' => TotalizerStub::class,
                'totalizadores' => [
                    'enabled' => true,
                    'columns' => [['field' => 'amount', 'aggregate' => 'sum']],
                ],
                'ui' => ['showTotalizador' => true],
                'permissions' => [],
            ],
        ]);
        TotalizerStub::create(['name' => 'A', 'amount' => 7]);

        $controller = new ExportController;
        $method = new ReflectionMethod($controller, 'getTotalizers');
        $result = $method->invoke($controller, TotalizerStub::query(), TotalizerStub::class);

        $this->assertCount(1, $result);
        $this->assertEquals(7, $result[0]['value']);
    }

    #[Test]
    public function a_legitimate_field_still_gets_summed(): void
    {
        $this->configureStub([
            ['field' => 'amount', 'aggregate' => 'sum'],
        ]);
        TotalizerStub::create(['name' => 'A', 'amount' => 10]);
        TotalizerStub::create(['name' => 'B', 'amount' => 25]);

        $result = $this->callGetTotalizers();

        $this->assertCount(1, $result);
        $this->assertSame('amount', $result[0]['field']);
        $this->assertEquals(35, $result[0]['value']);
    }

    #[Test]
    public function a_malicious_field_is_skipped_silently(): void
    {
        $this->configureStub([
            ['field' => 'id) FROM users --', 'aggregate' => 'sum'],
        ]);
        TotalizerStub::create(['name' => 'A', 'amount' => 10]);

        $result = $this->callGetTotalizers();

        $this->assertSame([], $result);
    }

    #[Test]
    public function a_malicious_field_is_skipped_while_the_legitimate_one_beside_it_still_works(): void
    {
        $this->configureStub([
            ['field' => 'id) FROM users --', 'aggregate' => 'sum'],
            ['field' => 'amount', 'aggregate' => 'sum'],
        ]);
        TotalizerStub::create(['name' => 'A', 'amount' => 10]);
        TotalizerStub::create(['name' => 'B', 'amount' => 5]);

        $result = $this->callGetTotalizers();

        $this->assertCount(1, $result);
        $this->assertSame('amount', $result[0]['field']);
        $this->assertEquals(15, $result[0]['value']);
    }
}
