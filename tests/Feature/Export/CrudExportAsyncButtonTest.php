<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

/** Plain stub on the shared `items` table (see tests/migrations). */
class AsyncButtonStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Covers the toolbar's "Export in background" button (_toolbar.blade.php):
 * shown only when exportConfig.asyncExport.enabled is opted in, and the
 * "requires a queue" hint only when config('queue.default') is 'sync'.
 */
class CrudExportAsyncButtonTest extends TestCase
{
    private function makeConfig(array $exportConfig): void
    {
        CrudConfig::create([
            'model' => AsyncButtonStub::class,
            'route' => '',
            'config' => [
                'crud' => AsyncButtonStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'exportConfig' => $exportConfig,
                'permissions' => [],
            ],
        ]);
    }

    #[Test]
    public function the_async_button_is_shown_when_opted_in(): void
    {
        $this->makeConfig(['enabled' => true, 'asyncExport' => ['enabled' => true]]);

        Livewire::test(BaseCrud::class, ['model' => AsyncButtonStub::class])
            ->assertSee(__('ptah::ui.btn_export_async'));
    }

    #[Test]
    public function the_async_button_is_hidden_when_not_opted_in(): void
    {
        // enabled, but no asyncExport key at all (pre-Fase-3 config shape).
        $this->makeConfig(['enabled' => true]);

        Livewire::test(BaseCrud::class, ['model' => AsyncButtonStub::class])
            ->assertDontSee(__('ptah::ui.btn_export_async'));
    }

    #[Test]
    public function the_async_button_is_hidden_when_async_export_is_explicitly_off(): void
    {
        $this->makeConfig(['enabled' => true, 'asyncExport' => ['enabled' => false]]);

        Livewire::test(BaseCrud::class, ['model' => AsyncButtonStub::class])
            ->assertDontSee(__('ptah::ui.btn_export_async'));
    }

    #[Test]
    public function the_requires_queue_hint_is_shown_when_the_queue_connection_is_sync(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync by default — no override needed.
        $this->makeConfig(['enabled' => true, 'asyncExport' => ['enabled' => true]]);

        Livewire::test(BaseCrud::class, ['model' => AsyncButtonStub::class])
            ->assertSee(__('ptah::ui.export_requires_queue'));
    }

    #[Test]
    public function the_requires_queue_hint_is_hidden_when_a_real_queue_is_configured(): void
    {
        config(['queue.default' => 'database']);
        $this->makeConfig(['enabled' => true, 'asyncExport' => ['enabled' => true]]);

        Livewire::test(BaseCrud::class, ['model' => AsyncButtonStub::class])
            ->assertDontSee(__('ptah::ui.export_requires_queue'));
    }
}
