<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\PermissionAudit;
use Ptah\Tests\TestCase;

/**
 * Covers `ptah:audit-prune` — deletes `ptah_permission_audits` rows older
 * than the retention window. DESTRUCTIVE by nature; these tests only run
 * against the disposable in-memory sqlite test database.
 */
class AuditPruneCommandTest extends TestCase
{
    private function makeAudit(int $daysOld, string $result = 'granted'): PermissionAudit
    {
        $audit = PermissionAudit::create([
            'user_id' => 1,
            'company_id' => null,
            'resource_key' => 'products.index',
            'action' => 'read',
            'result' => $result,
        ]);

        // PermissionAudit::UPDATED_AT is null and created_at isn't guarded by
        // the model's own timestamps() call here, but Eloquent still stamps
        // it on create() — override directly to simulate an old row.
        $audit->timestamps = false;
        $audit->created_at = now()->subDays($daysOld);
        $audit->save();

        return $audit;
    }

    #[Test]
    public function it_deletes_only_rows_older_than_the_window(): void
    {
        $old = $this->makeAudit(100);
        $recent = $this->makeAudit(10);

        $this->artisan('ptah:audit-prune', ['--days' => 90])->assertExitCode(0);

        $this->assertNull(PermissionAudit::find($old->id));
        $this->assertNotNull(PermissionAudit::find($recent->id));
    }

    #[Test]
    public function dry_run_counts_but_deletes_nothing(): void
    {
        $this->makeAudit(100);
        $this->makeAudit(100);

        $this->artisan('ptah:audit-prune', ['--days' => 90, '--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);

        $this->assertSame(2, PermissionAudit::query()->count());
    }

    #[Test]
    public function days_zero_is_refused(): void
    {
        $this->makeAudit(100);

        $this->artisan('ptah:audit-prune', ['--days' => 0])
            ->assertExitCode(1);

        $this->assertSame(1, PermissionAudit::query()->count());
    }

    #[Test]
    public function negative_days_is_refused(): void
    {
        $this->artisan('ptah:audit-prune', ['--days' => -5])
            ->assertExitCode(1);
    }

    #[Test]
    public function chunk_of_one_still_deletes_every_matching_row(): void
    {
        $this->makeAudit(100);
        $this->makeAudit(100);
        $this->makeAudit(100);

        $this->artisan('ptah:audit-prune', ['--days' => 90, '--chunk' => 1])->assertExitCode(0);

        $this->assertSame(0, PermissionAudit::query()->count());
    }

    #[Test]
    public function an_empty_table_is_a_no_op(): void
    {
        $this->artisan('ptah:audit-prune', ['--days' => 90])
            ->expectsOutputToContain('Nenhum registro')
            ->assertExitCode(0);

        $this->assertSame(0, PermissionAudit::query()->count());
    }

    #[Test]
    public function it_defaults_to_the_configured_retention_when_days_is_omitted(): void
    {
        config(['ptah.permissions.audit_retention_days' => 5]);

        $old = $this->makeAudit(10);
        $recent = $this->makeAudit(1);

        $this->artisan('ptah:audit-prune')->assertExitCode(0);

        $this->assertNull(PermissionAudit::find($old->id));
        $this->assertNotNull(PermissionAudit::find($recent->id));
    }
}
