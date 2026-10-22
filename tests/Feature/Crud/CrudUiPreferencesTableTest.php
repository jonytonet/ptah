<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class UiPrefsItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * 1.50.1: uiPreferences.stickyHeader / highlightOnHover were written by the
 * editor and read by nothing — the header was always sticky and every row
 * always highlighted.
 */
class CrudUiPreferencesTableTest extends TestCase
{
    private function html(array $ui): string
    {
        CrudConfig::create(['model' => UiPrefsItem::class, 'route' => '', 'config' => [
            'crud' => UiPrefsItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']],
            'uiPreferences' => $ui,
            'permissions' => [],
        ]]);

        return Livewire::test(BaseCrud::class, ['model' => UiPrefsItem::class])->call('clearFilters')->html();
    }

    #[Test]
    public function both_off_mark_the_table(): void
    {
        $html = $this->html(['stickyHeader' => false, 'highlightOnHover' => false]);

        $this->assertStringContainsString('ptah-no-sticky-head', $html);
        $this->assertStringContainsString('ptah-no-row-hover', $html);
    }

    #[Test]
    public function the_defaults_keep_both_on(): void
    {
        $html = $this->html([]);

        $this->assertStringNotContainsString('ptah-no-sticky-head', $html);
        $this->assertStringNotContainsString('ptah-no-row-hover', $html);
    }
}
