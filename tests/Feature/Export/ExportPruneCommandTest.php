<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Export;
use Ptah\Tests\TestCase;

/**
 * Covers `ptah:export-prune` — deletes the generated file (if any) and the
 * row for every Export whose expires_at has passed, AND every orphaned
 * (never reached 'done') row older than the same ttl_hours window; leaves
 * everything else untouched.
 */
class ExportPruneCommandTest extends TestCase
{
    #[Test]
    public function it_prunes_expired_exports_and_their_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/expired.xlsx', 'content');

        $expired = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'done',
            'file_disk' => 'local', 'file_path' => 'ptah-exports/expired.xlsx',
            'payload' => [], 'expires_at' => now()->subDay(),
        ]);

        $stillValid = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'done',
            'file_disk' => 'local', 'file_path' => 'ptah-exports/valid.xlsx',
            'payload' => [], 'expires_at' => now()->addDay(),
        ]);

        $neverExpires = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'queued',
            'payload' => [],
        ]);

        $this->artisan('ptah:export-prune')->assertSuccessful();

        $this->assertNull(Export::find($expired->id));
        $this->assertNotNull(Export::find($stillValid->id));
        $this->assertNotNull(Export::find($neverExpires->id));
        Storage::disk('local')->assertMissing('ptah-exports/expired.xlsx');
    }

    #[Test]
    public function it_is_a_no_op_when_nothing_is_expired(): void
    {
        Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'queued',
            'payload' => [],
        ]);

        $this->artisan('ptah:export-prune')->assertSuccessful();

        $this->assertSame(1, Export::query()->count());
    }

    #[Test]
    public function it_prunes_orphaned_non_done_exports_older_than_the_ttl(): void
    {
        // Never reached 'done' (no expires_at) but old enough (> ttl_hours,
        // default 48h, from created_at) to be an orphan: a crashed worker, a
        // job that never ran, a failure nobody ever looked at.
        $oldFailed = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'failed',
            'payload' => [], 'error' => 'boom',
        ]);
        $oldFailed->timestamps = false;
        $oldFailed->created_at = now()->subHours(49);
        $oldFailed->save();

        $oldQueued = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'queued',
            'payload' => [],
        ]);
        $oldQueued->timestamps = false;
        $oldQueued->created_at = now()->subHours(72);
        $oldQueued->save();

        $freshQueued = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'queued',
            'payload' => [],
        ]);

        $this->artisan('ptah:export-prune')->assertSuccessful();

        $this->assertNull(Export::find($oldFailed->id));
        $this->assertNull(Export::find($oldQueued->id));
        $this->assertNotNull(Export::find($freshQueued->id));
    }

    #[Test]
    public function it_prunes_the_file_of_an_orphaned_export_when_one_somehow_exists(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/orphan.xlsx', 'content');

        $orphan = Export::create([
            'user_id' => 1, 'model' => 'Foo', 'format' => 'excel', 'status' => 'failed',
            'file_disk' => 'local', 'file_path' => 'ptah-exports/orphan.xlsx',
            'payload' => [], 'error' => 'boom',
        ]);
        $orphan->timestamps = false;
        $orphan->created_at = now()->subHours(49);
        $orphan->save();

        $this->artisan('ptah:export-prune')->assertSuccessful();

        $this->assertNull(Export::find($orphan->id));
        Storage::disk('local')->assertMissing('ptah-exports/orphan.xlsx');
    }
}
