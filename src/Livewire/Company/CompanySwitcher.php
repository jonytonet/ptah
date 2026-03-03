<?php

declare(strict_types=1);

namespace Ptah\Livewire\Company;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Ptah\Models\Company;
use Ptah\Services\Company\CompanyService;

/**
 * CompanySwitcher — Displayed in the Navbar.
 *
 * - Initialises the company session on mount (if not yet set).
 * - Shows a badge with abbreviation if only 1 company, or a dropdown if multiple.
 * - When switching company, reloads the page to refresh all CRUDs.
 */
class CompanySwitcher extends Component
{
    /** @var \Illuminate\Support\Collection<Company> */
    public $companies = [];

    /** ID da empresa ativa */
    public int $activeId = 0;

    /** Current page URL — captured in mount before any Livewire request */
    public string $pageUrl = '';

    protected CompanyService $companyService;

    public function boot(CompanyService $companyService): void
    {
        $this->companyService = $companyService;
    }

    public function mount(): void
    {
        // Capture the page URL BEFORE any Livewire (AJAX) request
        // request()->fullUrl() in later callbacks points to livewire/update
        $this->pageUrl = url()->current();

        $this->companyService->initSession();

        $this->companies = $this->companyService->getAll();
        $this->activeId  = $this->companyService->activeId();
    }

    /**
     * Switches to the selected company and reloads the page.
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
