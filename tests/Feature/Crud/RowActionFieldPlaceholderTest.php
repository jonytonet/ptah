<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class RowActionPlaceholderItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];

    protected $hidden = ['amount'];
}

/**
 * 1.50.0: the docs promised `%field%` in `actionValue`; only `%id%` was
 * replaced. Now `%field%` works in a LINK action, URL-encoded — never in a
 * livewire/javascript one, where the value would land inside JS.
 */
class RowActionFieldPlaceholderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('items')->insert(['name' => 'Alfa B&C', 'status' => 'active', 'amount' => 99, 'created_at' => now(), 'updated_at' => now()]);

        CrudConfig::create(['model' => RowActionPlaceholderItem::class, 'route' => '', 'config' => [
            'crud' => RowActionPlaceholderItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Abrir', 'colsTipo' => 'action',
                    'actionType' => 'link', 'actionValue' => '/busca?q=%name%&s=%status%&a=%amount%', 'actionIcon' => 'bx bx-link'],
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Rodar', 'colsTipo' => 'action',
                    'actionType' => 'livewire', 'actionValue' => 'run(%id%, "%name%")', 'actionIcon' => 'bx bx-play'],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function a_link_action_fills_field_placeholders_url_encoded(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => RowActionPlaceholderItem::class])->call('clearFilters')->html();

        $this->assertStringContainsString('/busca?q=Alfa%20B%26C&amp;s=active&amp;a=', $html);
        $this->assertStringNotContainsString('a=99', $html, 'Atributo $hidden nao e substituido.');
    }

    #[Test]
    public function a_livewire_action_keeps_field_placeholders_literal(): void
    {
        $html = Livewire::test(BaseCrud::class, ['model' => RowActionPlaceholderItem::class])->call('clearFilters')->html();

        $this->assertStringNotContainsString('Alfa B&amp;C&quot;)', $html);
        $this->assertStringContainsString('%name%', $html);
    }
}
