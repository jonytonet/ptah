<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Exports\ExportsPanel;
use Ptah\Models\Export;
use Ptah\Tests\TestCase;

class ExportsPanelUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers the ExportsPanel Livewire component (Fase 3 — "grande volume"
 * "Exportações" panel): per-user scoping, the conditional wire:poll, the
 * download link and file+row removal.
 */
class ExportsPanelTest extends TestCase
{
    private function makeUser(): ExportsPanelUser
    {
        return ExportsPanelUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    #[Test]
    public function it_lists_only_the_current_users_exports(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();

        Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
        ]);
        Export::create([
            'user_id' => $other->id, 'model' => 'Bar', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
        ]);

        $this->actingAs($me);

        $component = Livewire::test(ExportsPanel::class);

        $this->assertCount(1, $component->viewData('exports'));
        $component->assertSee('Foo')->assertDontSee('Bar');
    }

    #[Test]
    public function has_pending_is_true_while_a_row_is_queued_or_processing(): void
    {
        $me = $this->makeUser();
        Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'queued', 'payload' => [],
        ]);

        $this->actingAs($me);

        $this->assertTrue(Livewire::test(ExportsPanel::class)->viewData('hasPending'));
    }

    #[Test]
    public function has_pending_is_false_once_everything_has_settled(): void
    {
        $me = $this->makeUser();
        Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
        ]);
        Export::create([
            'user_id' => $me->id, 'model' => 'Bar', 'format' => 'excel',
            'status' => 'failed', 'payload' => [],
        ]);

        $this->actingAs($me);

        $component = Livewire::test(ExportsPanel::class);

        $this->assertFalse($component->viewData('hasPending'));
        $component->assertDontSeeHtml('wire:poll');
    }

    #[Test]
    public function wire_poll_is_rendered_while_something_is_pending(): void
    {
        $me = $this->makeUser();
        Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'processing', 'payload' => [],
        ]);

        $this->actingAs($me);

        Livewire::test(ExportsPanel::class)->assertSeeHtml('wire:poll');
    }

    #[Test]
    public function the_download_link_points_to_the_gated_file_route(): void
    {
        $me = $this->makeUser();
        $export = Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
            'file_disk' => 'local', 'file_path' => 'ptah-exports/foo.xlsx',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($me);

        Livewire::test(ExportsPanel::class)
            ->assertSeeHtml(route('ptah.export.file', ['export' => $export->id]));
    }

    #[Test]
    public function remove_deletes_the_file_and_the_row_when_owned_by_the_current_user(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/foo.xlsx', 'content');

        $me = $this->makeUser();
        $export = Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
            'file_disk' => 'local', 'file_path' => 'ptah-exports/foo.xlsx',
        ]);

        $this->actingAs($me);

        Livewire::test(ExportsPanel::class)->call('remove', $export->id);

        $this->assertNull(Export::find($export->id));
        Storage::disk('local')->assertMissing('ptah-exports/foo.xlsx');
    }

    #[Test]
    public function remove_does_nothing_for_an_export_owned_by_another_user(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        $export = Export::create([
            'user_id' => $other->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'done', 'payload' => [],
        ]);

        $this->actingAs($me);

        Livewire::test(ExportsPanel::class)->call('remove', $export->id);

        $this->assertNotNull(Export::find($export->id));
    }

    #[Test]
    public function the_failure_reason_is_visible_when_an_export_failed(): void
    {
        $me = $this->makeUser();
        Export::create([
            'user_id' => $me->id, 'model' => 'Foo', 'format' => 'excel',
            'status' => 'failed', 'payload' => [], 'error' => 'Export not allowed for this model.',
        ]);

        $this->actingAs($me);

        Livewire::test(ExportsPanel::class)
            ->assertSee(__('ptah::ui.export_status_failed'))
            ->assertSee('Export not allowed for this model.');
    }
}
