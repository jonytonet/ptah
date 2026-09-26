<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class DefaultItem extends Model
{
    protected $table = 'default_items';

    protected $fillable = ['name', 'is_active', 'sort_order'];
}

/**
 * Achado #4 do PetPlace: `colsDefaultValue` era gravado pelo wizard e lido
 * por ninguem — o "Novo" abria vazio, e numa coluna NOT NULL sem default no
 * banco, salvar so com os obrigatorios dava erro SQL cru.
 */
class CrudDefaultValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('default_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_active'); // NOT NULL, sem default no banco
            $t->integer('sort_order');
            $t->timestamps();
        });

        CrudConfig::create(['model' => DefaultItem::class, 'route' => '', 'config' => [
            'crud' => DefaultItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'is_active', 'colsNomeLogico' => 'Ativo', 'colsTipo' => 'boolean', 'colsGravar' => true, 'colsDefaultValue' => 'S'],
                ['colsNomeFisico' => 'sort_order', 'colsNomeLogico' => 'Ordem', 'colsTipo' => 'number', 'colsGravar' => true, 'colsDefaultValue' => '0'],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function new_opens_with_the_declared_defaults(): void
    {
        Livewire::test(BaseCrud::class, ['model' => DefaultItem::class])
            ->call('prepareCreate')
            ->assertSet('formData.is_active', true)
            ->assertSet('formData.sort_order', '0');
    }

    #[Test]
    public function saving_with_only_the_required_field_uses_the_defaults(): void
    {
        Livewire::test(BaseCrud::class, ['model' => DefaultItem::class])
            ->call('prepareCreate')
            ->set('formData.name', 'Banho')
            ->call('save')
            ->assertSet('formErrors', []);

        $item = DefaultItem::first();
        $this->assertNotNull($item, 'Sem os padroes, a coluna NOT NULL derrubava o INSERT.');
        $this->assertSame(1, (int) $item->is_active);
        $this->assertSame(0, (int) $item->sort_order);
    }

    #[Test]
    public function the_cli_writes_the_default(): void
    {
        $this->artisan('ptah:config', ['model' => DefaultItem::class, '--column' => ['is_active:boolean:label=Ativo:default=1'], '--non-interactive' => true])
            ->assertExitCode(0);

        $cfg = CrudConfig::where('model', ModelKey::canonical(DefaultItem::class))->first()->config;
        $col = collect($cfg['cols'])->firstWhere('colsNomeFisico', 'is_active');
        $this->assertEquals(1, $col['colsDefaultValue']);
    }
}
