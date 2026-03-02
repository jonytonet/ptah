<?php

declare(strict_types=1);

namespace Ptah\Livewire\Company;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Ptah\Models\Company;
use Ptah\Services\Company\CompanyService;

/**
 * CompanySwitcher — Exibido na Navbar.
 *
 * - Inicializa a sessão de empresa ao montar (se ainda não definida).
 * - Exibe badge com sigla se apenas 1 empresa, ou dropdown se múltiplas.
 * - Ao trocar de empresa, recarrega a página para atualizar todos os CRUDs.
 */
class CompanySwitcher extends Component
{
    /** @var \Illuminate\Support\Collection<Company> */
    public $companies = [];

    /** ID da empresa ativa */
    public int $activeId = 0;

    /** URL da página atual — capturada no mount antes de qualquer request Livewire */
    public string $pageUrl = '';

    protected CompanyService $companyService;

    public function boot(CompanyService $companyService): void
    {
        $this->companyService = $companyService;
    }

    public function mount(): void
    {
        // Captura a URL da página ANTES de qualquer request Livewire (AJAX)
        // request()->fullUrl() em callbacks posteriores aponta para livewire/update
        $this->pageUrl = url()->current();

        $this->companyService->initSession();

        $this->companies = $this->companyService->getAll();
        $this->activeId  = $this->companyService->activeId();
    }

    /**
     * Troca para a empresa selecionada e recarrega a página.
     */
    public function switchTo(int $id): void
    {
        $this->companyService->setActive($id);
        $this->redirect($this->pageUrl);
    }

    #[Computed]
    public function activeCompany(): ?Company
    {
        return $this->companies->firstWhere('id', $this->activeId);
    }

    public function render()
    {
        return view('ptah::livewire.company.company-switcher', [
            'activeCompany' => $this->companies->firstWhere('id', $this->activeId),
        ]);
    }
}
