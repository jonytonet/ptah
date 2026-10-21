<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class AuditFixStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * 1.50.0: ptah:config options the docs described and the code did not honour
 * (docs audit of 1.49.1).
 */
class ConfigCommandAuditFixesTest extends TestCase
{
    private function config(): array
    {
        return CrudConfig::where('model', ModelKey::canonical(AuditFixStub::class))->first()?->config ?? [];
    }

    #[Test]
    public function only_accepts_a_comma_separated_list(): void
    {
        // `--only=columns,filters` was the single value 'columns,filters': no
        // section matched, nothing was written, and it said "saved".
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--column' => ['name:text:label=Nome'],
            '--set' => ['displayName=Itens'],
            '--only' => ['columns,filters'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = $this->config();
        $this->assertSame('name', $config['cols'][0]['colsNomeFisico'] ?? null, 'columns foi processado.');
        $this->assertArrayNotHasKey('displayName', $config, 'general ficou de fora.');
    }

    #[Test]
    public function skip_accepts_a_comma_separated_list(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--column' => ['name:text:label=Nome'],
            '--set' => ['displayName=Itens'],
            '--skip' => ['columns,general'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = $this->config();
        $this->assertSame([], $config['cols'] ?? [], 'columns pulado.');
        $this->assertArrayNotHasKey('displayName', $config, 'general pulado.');
    }

    #[Test]
    public function an_unknown_section_is_refused(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--only' => ['colums'],
            '--non-interactive' => true,
        ])->expectsOutputToContain('Unknown section(s): colums')->assertExitCode(1);
    }

    #[Test]
    public function force_says_it_has_no_effect(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--set' => ['displayName=Itens'],
            '--force' => true,
            '--non-interactive' => true,
        ])->expectsOutputToContain('--force has no effect')->assertExitCode(0);
    }

    #[Test]
    public function export_formats_is_saved_as_a_list(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--set' => ['exportFormats=excel, pdf'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $this->assertSame(['excel', 'pdf'], $this->config()['exportConfig']['formats'] ?? null);
    }

    #[Test]
    public function a_join_is_saved_in_the_shape_the_runtime_reads(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--join' => ['left:users:items.owner_id=users.id:distinct=true:select=users.name:owner_name'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $join = $this->config()['joins'][0] ?? [];
        $this->assertSame('users', $join['table'] ?? null);
        $this->assertTrue($join['distinct'] ?? false);
        $this->assertSame([['column' => 'users.name', 'alias' => 'owner_name']], $join['select'] ?? null);
    }

    #[Test]
    public function a_join_where_is_refused_and_nothing_is_saved(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--join' => ['left:categories:items.category_id=categories.id:where=categories.active=1'],
            '--non-interactive' => true,
        ])->expectsOutputToContain('JOIN option "where" is not supported')->assertExitCode(1);

        $this->assertSame([], $this->config());
    }

    #[Test]
    public function totalizer_on_a_column_goes_to_the_screen_totals_the_runtime_reads(): void
    {
        $this->artisan('ptah:config', [
            'model' => AuditFixStub::class,
            '--column' => ['amount:number:totalizer=sum:totalizer_format=currency'],
            '--non-interactive' => true,
        ])->expectsOutputToContain('totalizadorFormat has no effect')->assertExitCode(0);

        $config = $this->config();
        $this->assertTrue($config['totalizadores']['enabled'] ?? false);
        $this->assertSame([['field' => 'amount', 'aggregate' => 'sum']], $config['totalizadores']['columns'] ?? null);
        $this->assertArrayNotHasKey('totalizadorType', $config['cols'][0]);
    }
}
