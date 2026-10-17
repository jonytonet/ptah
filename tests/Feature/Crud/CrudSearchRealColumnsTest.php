<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class SrcPerson extends Model
{
    protected $table = 'src_people';

    protected $guarded = [];

    public $timestamps = false;
}

class SrcClient extends Model
{
    protected $table = 'src_clients';

    protected $guarded = [];

    public $timestamps = false;

    public function person(): BelongsTo
    {
        return $this->belongsTo(SrcPerson::class, 'person_id');
    }

    /** Shown on the listing — not a column. */
    public function getFullNameAttribute(): string
    {
        return $this->company_name ?: (string) $this->person?->name;
    }
}

class SrcOrder extends Model
{
    protected $table = 'src_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function client(): BelongsTo
    {
        return $this->belongsTo(SrcClient::class, 'client_id');
    }
}

/**
 * 1.46.1 (PetPlace, 88 screens swept): the global search broke on 20 screens,
 * and the CPF column filter found nothing typed with its mask or partially.
 */
class CrudSearchRealColumnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('src_people', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('src_clients', fn (Blueprint $t) => [$t->id(), $t->string('company_name')->nullable(), $t->unsignedBigInteger('person_id')->nullable(), $t->string('cpf')->nullable()]);
        Schema::create('src_orders', fn (Blueprint $t) => [$t->id(), $t->string('code'), $t->unsignedBigInteger('client_id')]);

        $joao = SrcPerson::create(['name' => 'Joao Silva']);
        $acme = SrcClient::create(['company_name' => 'Acme Pet', 'cpf' => '11144477735']);
        $pessoa = SrcClient::create(['company_name' => null, 'person_id' => $joao->id, 'cpf' => '52998224725']);
        SrcOrder::create(['code' => 'P-001', 'client_id' => $acme->id]);
        SrcOrder::create(['code' => 'P-002', 'client_id' => $pessoa->id]);
    }

    private function orders(array $clientCol = []): void
    {
        CrudConfig::updateOrCreate(['model' => SrcOrder::class, 'route' => ''], ['config' => [
            'crud' => SrcOrder::class,
            'cols' => [
                ['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Codigo', 'colsTipo' => 'text', 'colsIsFilterable' => 'S'],
                array_merge(['colsNomeFisico' => 'client_id', 'colsNomeLogico' => 'Cliente', 'colsTipo' => 'searchdropdown', 'colsIsFilterable' => 'S',
                    'colsRelacao' => 'client', 'colsRelacaoExibe' => 'full_name'], $clientCol),
            ],
            'permissions' => [],
        ]]);
    }

    private function codes($crud): array
    {
        $codes = collect($crud->viewData('rows')->items())->pluck('code')->all();
        sort($codes);

        return $codes;
    }

    private function orderList()
    {
        return Livewire::test(BaseCrud::class, ['model' => SrcOrder::class])->call('clearFilters');
    }

    #[Test]
    public function an_accessor_shown_by_a_relation_column_does_not_break_the_search(): void
    {
        $this->orders();

        // full_name e accessor: ficava LOWER(full_name) LIKE... e a lista vinha vazia.
        $this->assertSame(['P-001'], $this->codes($this->orderList()->set('search', 'P-001')));
    }

    #[Test]
    public function a_relation_column_can_say_where_to_search(): void
    {
        $this->orders(['colsRelacaoBusca' => ['company_name', 'person.name']]);

        $this->assertSame(['P-001'], $this->codes($this->orderList()->set('search', 'acme')));
        $this->assertSame(['P-002'], $this->codes($this->orderList()->set('search', 'joao')), 'Caminho aninhado (person.name).');
        // E o filtro de texto da propria coluna usa os mesmos alvos.
        $this->assertSame(['P-002'], $this->codes($this->orderList()->set('filters.client_id', 'joao')->set('filterOperators.client_id', 'LIKE')));
    }

    #[Test]
    public function a_column_marked_not_searchable_stays_out_of_the_search(): void
    {
        $this->orders(['colsRelacaoBusca' => 'company_name', 'colsSearchable' => false]);

        $this->assertSame([], $this->codes($this->orderList()->set('search', 'acme')));
    }

    #[Test]
    public function a_search_that_still_fails_says_so_instead_of_an_empty_list(): void
    {
        // Um JOIN que nao existe: o SQL quebra de verdade.
        CrudConfig::updateOrCreate(['model' => SrcOrder::class, 'route' => ''], ['config' => [
            'crud' => SrcOrder::class,
            'cols' => [['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Codigo', 'colsTipo' => 'text', 'colsSource' => 'nope_table.code']],
            'permissions' => [],
        ]]);
        Exceptions::fake();

        $crud = $this->orderList()->set('search', 'P-001');

        $crud->assertDispatched('ptah-toast', title: __('ptah::ui.crud_search_error', ['term' => 'P-001']), color: 'danger');
        $this->assertSame([], $this->codes($crud));
        Exceptions::assertReported(QueryException::class);
    }

    private function clients(): void
    {
        CrudConfig::updateOrCreate(['model' => SrcClient::class, 'route' => ''], ['config' => [
            'crud' => SrcClient::class,
            'cols' => [
                ['colsNomeFisico' => 'company_name', 'colsNomeLogico' => 'Empresa', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'cpf', 'colsNomeLogico' => 'CPF', 'colsTipo' => 'text', 'colsMask' => 'cpf',
                    'colsMaskTransform' => 'digits_only', 'colsIsFilterable' => true],
            ],
            'permissions' => [],
        ]]);
    }

    private function cpfs($crud): array
    {
        return collect($crud->viewData('rows')->items())->pluck('cpf')->all();
    }

    #[Test]
    public function the_cpf_filter_finds_it_typed_with_the_mask_without_it_and_partially(): void
    {
        $this->clients();
        $list = fn () => Livewire::test(BaseCrud::class, ['model' => SrcClient::class])->call('clearFilters');

        foreach (['52998224725', '529.982.247-25', '529982'] as $typed) {
            $this->assertSame(['52998224725'], $this->cpfs($list()->set('filters.cpf', $typed)), "Filtro com \"{$typed}\".");
            $this->assertSame(['52998224725'], $this->cpfs($list()->set('search', $typed)), "Busca geral com \"{$typed}\".");
        }
    }
}
