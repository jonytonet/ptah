<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Models;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Company;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

/**
 * Testes unitários do modelo Company.
 *
 * Cobertura:
 *  - getLabelDisplay() com label configurado
 *  - getLabelDisplay() fallback para iniciais do nome
 *  - Auto-geração de slug ao criar
 *  - Scopes active() e default()
 *  - SoftDeletes funcionando
 */
class CompanyModelTest extends TestCase
{
    // ── getLabelDisplay ────────────────────────────────────────────────

    #[Test]
    public function retorna_label_configurado_em_maiusculo(): void
    {
        $company = CompanyFactory::new()->make(['label' => 'acme']);

        $this->assertSame('ACME', $company->getLabelDisplay());
    }

    #[Test]
    public function retorna_iniciais_quando_label_e_nulo(): void
    {
        $company = CompanyFactory::new()->make(['name' => 'Acme Corp Solutions', 'label' => null]);

        // Iniciais de cada palavra: A, C, S = "ACS"
        $this->assertSame('ACS', $company->getLabelDisplay());
    }

    #[Test]
    public function retorna_iniciais_quando_label_e_string_vazia(): void
    {
        $company = CompanyFactory::new()->make(['name' => 'Beta Tecnologia', 'label' => '']);

        $this->assertSame('BT', $company->getLabelDisplay());
    }

    #[Test]
    public function label_display_retorna_maximo_de_4_chars(): void
    {
        $company = CompanyFactory::new()->make([
            'name'  => 'Alpha Beta Gamma Delta Epsilon',
            'label' => null,
        ]);

        $this->assertLessThanOrEqual(4, strlen($company->getLabelDisplay()));
    }

    #[Test]
    public function label_display_para_nome_de_palavra_unica(): void
    {
        $company = CompanyFactory::new()->make(['name' => 'Ptah', 'label' => null]);

        $this->assertSame('P', $company->getLabelDisplay());
    }

    // ── Scopes ────────────────────────────────────────────────────────

    #[Test]
    public function scope_active_retorna_apenas_empresas_ativas(): void
    {
        CompanyFactory::new()->create(['is_active' => true,  'name' => 'Ativa',   'label' => 'ATIV']);
        CompanyFactory::new()->create(['is_active' => false, 'name' => 'Inativa', 'label' => 'INAT']);

        $active = Company::active()->get();

        $this->assertCount(1, $active);
        $this->assertSame('Ativa', $active->first()->name);
    }

    #[Test]
    public function scope_default_retorna_empresa_padrao(): void
    {
        CompanyFactory::new()->create(['is_default' => true,  'name' => 'Padrão', 'label' => 'PADR']);
        CompanyFactory::new()->create(['is_default' => false, 'name' => 'Outra',  'label' => 'OUTR']);

        $default = Company::default()->get();

        $this->assertCount(1, $default);
        $this->assertSame('Padrão', $default->first()->name);
    }

    // ── Auto-slug ──────────────────────────────────────────────────────

    #[Test]
    public function auto_gera_slug_ao_criar(): void
    {
        $company = CompanyFactory::new()->create(['name' => 'Minha Empresa', 'label' => 'MINE']);

        $this->assertSame('minha-empresa', $company->fresh()->slug);
    }

    #[Test]
    public function nao_sobrescreve_slug_se_ja_definido(): void
    {
        $company = CompanyFactory::new()->create([
            'name'  => 'Ptah Empresa',
            'label' => 'PTAH',
            'slug'  => 'meu-slug-custom',
        ]);

        $this->assertSame('meu-slug-custom', $company->fresh()->slug);
    }

    // ── Soft Deletes ───────────────────────────────────────────────────

    #[Test]
    public function soft_delete_nao_remove_registro_do_banco(): void
    {
        $company = CompanyFactory::new()->create(['label' => 'SFTD']);

        $company->delete();

        $this->assertSoftDeleted('ptah_companies', ['id' => $company->id]);
        $this->assertNull(Company::find($company->id));
        $this->assertNotNull(Company::withTrashed()->find($company->id));
    }
}
