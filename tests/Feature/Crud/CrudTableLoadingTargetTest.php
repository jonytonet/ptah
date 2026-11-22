<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class TableLoadingItem extends Model
{
    protected $table = 'table_loading_items';

    protected $guarded = [];
}

/**
 * The table's loading state ignores what the form does (1.60.2, yelll).
 *
 * The bar above the table and the dimmed table had no wire:target, so they lit
 * up on every request of the component: with the form open, each focus on a
 * searchDropdown and each select chosen made the table behind the modal flash.
 * FormSelectLoopBrowserTest proves it in a browser; this pins the attributes.
 */
class CrudTableLoadingTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('table_loading_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });

        CrudConfig::updateOrCreate(['model' => TableLoadingItem::class, 'route' => ''], ['config' => [
            'crud' => TableLoadingItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function the_table_loading_ignores_form_requests(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => TableLoadingItem::class])
            ->set('viewMode', 'table')
            ->html();

        foreach (['ptah-c-tbl_wrap', 'h-0.5 mb-2'] as $marker) {
            $this->assertTrue(
                (bool) preg_match('/class="[^"]*'.preg_quote($marker, '/').'[^>]*wire:target\.except="([^"]+)"|wire:target\.except="([^"]+)"[^>]*class="[^"]*'.preg_quote($marker, '/').'/', $html, $m),
                "The element [{$marker}] has no wire:target.except."
            );
            $targets = explode(',', $m[1] !== '' ? $m[1] : $m[2]);

            foreach (['formData', 'openDropdown', 'searchDropdown', 'selectDropdownOption', 'openCreate', 'closeModal'] as $formOnly) {
                $this->assertContains($formOnly, $targets, "[{$marker}] lights up for {$formOnly}.");
            }
            // What changes the rows must still light it up.
            foreach (['search', 'filters', 'save', 'deleteRecord', 'gotoPage', 'sortBy'] as $listAction) {
                $this->assertNotContains($listAction, $targets);
            }
        }
    }

    #[Test]
    public function the_cards_loading_ignores_form_requests(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => TableLoadingItem::class])
            ->set('viewMode', 'cards')
            ->html();

        $this->assertMatchesRegularExpression('/wire:loading\.class="opacity-60" wire:target\.except="[^"]*formData[^"]*"/', $html);
    }
}
