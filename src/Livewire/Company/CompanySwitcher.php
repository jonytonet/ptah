<?php

declare(strict_types=1);

namespace Ptah\Livewire\Company;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
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
    /** @var Collection<Company> */
    public $companies = [];

    /** ID da empresa ativa */
    public int $activeId = 0;

    /** Current page URL — captured in mount before any Livewire request */
    public string $pageUrl = '';

    /**
     * 'inline' (default) — the horizontal group the navbar shows on wide
     * screens: active company name, separator, one tab per company.
     * 'stacked' — a vertical list of menu items, for use INSIDE a dropdown
     * panel. On a phone the inline group collided with the navbar's own icons
     * (reported from a production ERP), so the navbar hides it there and hosts
     * this variant inside the admin menu instead — one menu on the right
     * rather than two controls fighting for the same 60px.
     *
     * #[Locked]: the layout is chosen by the call site in a Blade template, and
     * a client that could rewrite it would only be reshaping its own dropdown —
     * harmless, but there is no reason for it to be writable either.
     */
    #[Locked]
    public string $layout = 'inline';

    protected CompanyService $companyService;

    public function boot(CompanyService $companyService): void
    {
        $this->companyService = $companyService;
    }

    public function mount(string $layout = 'inline'): void
    {
        $this->layout = $layout === 'stacked' ? 'stacked' : 'inline';

        // Capture the page URL BEFORE any Livewire (AJAX) request
        // request()->fullUrl() in later callbacks points to livewire/update
        $this->pageUrl = url()->current();

        $this->companyService->initSession();

        $this->companies = $this->companyService->getAll();
        $this->activeId = $this->companyService->activeId();
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
