<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class DoctorStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

/**
 * Covers ptah:config:doctor — the author-time audit that catches the silent
 * failures the per-model tooling can't: orphan (non-canonical) keys, malformed
 * configs, empty screens and global-vs-route ambiguity.
 */
class ConfigDoctorCommandTest extends TestCase
{
    private function seedConfig(string $model, string $route, array $config): void
    {
        CrudConfig::create(['model' => $model, 'route' => $route, 'config' => $config]);
    }

    private function goodConfig(): array
    {
        return ['cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']]];
    }

    #[Test]
    public function clean_configs_pass(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')->assertExitCode(0);
    }

    #[Test]
    public function orphan_key_is_reported_then_fixed(): void
    {
        // Stored under the FQCN (backslashes) — the runtime reads the slash form.
        $this->seedConfig(DoctorStub::class, '', $this->goodConfig());
        $canonical = ModelKey::canonical(DoctorStub::class);

        // Without --fix: reported as an error.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('orphan key')
            ->assertExitCode(1);

        // With --fix: key rewritten to canonical, now clean.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $this->assertDatabaseHas('crud_configs', ['model' => $canonical]);
        $this->assertDatabaseMissing('crud_configs', ['model' => DoctorStub::class]);
    }

    #[Test]
    public function malformed_config_is_an_error(): void
    {
        // Column without colsNomeFisico → ConfigSchemaValidator rejects it.
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => [['colsTipo' => 'text']]]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('malformed')
            ->assertExitCode(1);
    }

    #[Test]
    public function empty_columns_warns_but_does_not_fail(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => []]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('no columns')
            ->assertExitCode(0);
    }

    #[Test]
    public function global_plus_route_specific_is_flagged(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $this->seedConfig($canonical, '', $this->goodConfig());
        $this->seedConfig($canonical, 'invoices', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('route fallback')
            ->assertExitCode(0);
    }
}
