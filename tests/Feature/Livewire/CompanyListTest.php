<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Livewire;

use Livewire\Livewire;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Models\Company;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

/**
 * Testes de feature do componente Livewire CompanyList.
 *
 * Cobertura:
 *  - Listagem de empresas
 *  - Criação com validação
 *  - Edição com validação
 *  - Unicidade do campo label
 *  - Exclusão (com proteção da empresa padrão)
 *  - Busca / filtro
 */
class CompanyListTest extends TestCase
{
    // ── Listagem ───────────────────────────────────────────────────────

    /** @test */
    public function componente_renderiza_sem_erros(): void
    {
        Livewire::test(CompanyList::class)
            ->assertOk();
    }

    /** @test */
    public function exibe_empresas_cadastradas(): void
    {
        CompanyFactory::new()->create(['name' => 'Empresa Alpha', 'label' => 'ALPH']);
        CompanyFactory::new()->create(['name' => 'Empresa Beta',  'label' => 'BETA']);

        Livewire::test(CompanyList::class)
            ->assertSee('Empresa Alpha')
            ->assertSee('Empresa Beta');
    }

    // ── Criação ────────────────────────────────────────────────────────

    /** @test */
    public function pode_criar_empresa_valida(): void
    {
        Livewire::test(CompanyList::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->set('name', 'Nova Empresa Ltda')
            ->set('label', 'NOVA')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('ptah_companies', [
            'name'  => 'Nova Empresa Ltda',
            'label' => 'NOVA',
        ]);
    }

    /** @test */
    public function nome_e_obrigatorio(): void
    {
        Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    /** @test */
    public function label_nao_pode_ter_mais_de_4_caracteres(): void
    {
        Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', 'Empresa Teste')
            ->set('label', 'TOOLONG')
            ->call('save')
            ->assertHasErrors(['label' => 'max']);
    }

    /** @test */
    public function label_deve_ser_unico(): void
    {
        CompanyFactory::new()->create(['name' => 'Existente', 'label' => 'DUPL']);

        Livewire::test(CompanyList::class)
            ->call('create')
            ->set('name', 'Nova Empresa')
            ->set('label', 'DUPL')
            ->call('save')
            ->assertHasErrors(['label']);
    }

    // ── Edição ─────────────────────────────────────────────────────────

    /** @test */
    public function pode_editar_empresa_existente(): void
    {
        $company = CompanyFactory::new()->create(['name' => 'Original', 'label' => 'ORIG']);

        Livewire::test(CompanyList::class)
            ->call('edit', $company->id)
            ->assertSet('showModal', true)
            ->assertSet('name', 'Original')
            ->set('name', 'Atualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ptah_companies', ['id' => $company->id, 'name' => 'Atualizado']);
    }

    /** @test */
    public function pode_salvar_sem_alterar_o_proprio_label(): void
    {
        $company = CompanyFactory::new()->create(['name' => 'Empresa', 'label' => 'KEPT']);

        Livewire::test(CompanyList::class)
            ->call('edit', $company->id)
            ->set('name', 'Empresa Renomeada')
            ->call('save')
            ->assertHasNoErrors(['label']);

        $this->assertDatabaseHas('ptah_companies', ['id' => $company->id, 'name' => 'Empresa Renomeada']);
    }

    /** @test */
    public function label_duplicado_de_outra_empresa_invalida_ao_editar(): void
    {
        CompanyFactory::new()->create(['name' => 'Outra',   'label' => 'DUPL']);
        $target = CompanyFactory::new()->create(['name' => 'Target', 'label' => 'TARG']);

        Livewire::test(CompanyList::class)
            ->call('edit', $target->id)
            ->set('label', 'DUPL')
            ->call('save')
            ->assertHasErrors(['label']);
    }

    // ── Exclusão ───────────────────────────────────────────────────────

    /** @test */
    public function pode_excluir_empresa_nao_padrao(): void
    {
        $company = CompanyFactory::new()->create(['name' => 'Excluível', 'label' => 'EXCL', 'is_default' => false]);

        Livewire::test(CompanyList::class)
            ->call('confirmDelete', $company->id)
            ->assertSet('showDeleteModal', true)
            ->call('delete')
            ->assertSet('showDeleteModal', false);

        $this->assertSoftDeleted('ptah_companies', ['id' => $company->id]);
    }

    /** @test */
    public function nao_pode_excluir_empresa_padrao(): void
    {
        $default = CompanyFactory::new()->create(['name' => 'Padrão', 'label' => 'DFLT', 'is_default' => true]);

        Livewire::test(CompanyList::class)
            ->call('confirmDelete', $default->id)
            ->call('delete');

        $this->assertDatabaseHas('ptah_companies', ['id' => $default->id, 'deleted_at' => null]);
    }

    // ── Busca ──────────────────────────────────────────────────────────

    /** @test */
    public function busca_filtra_por_nome(): void
    {
        CompanyFactory::new()->create(['name' => 'Empresa Encontrada',   'label' => 'FIND']);
        CompanyFactory::new()->create(['name' => 'Empresa Não Listada', 'label' => 'HIDE']);

        Livewire::test(CompanyList::class)
            ->set('search', 'Encontrada')
            ->assertSee('Empresa Encontrada')
            ->assertDontSee('Empresa Não Listada');
    }

    /** @test */
    public function limpar_busca_exibe_todas(): void
    {
        CompanyFactory::new()->create(['name' => 'Primeira',  'label' => 'PRI1']);
        CompanyFactory::new()->create(['name' => 'Segunda',   'label' => 'SEC2']);

        Livewire::test(CompanyList::class)
            ->set('search', 'Primeira')
            ->set('search', '')
            ->assertSee('Primeira')
            ->assertSee('Segunda');
    }
}
