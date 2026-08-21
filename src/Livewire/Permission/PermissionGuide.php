<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Ptah\Livewire\Permission\Concerns\RequiresMasterAccess;

#[Layout('ptah::layouts.forge-dashboard')]
class PermissionGuide extends Component
{
    use RequiresMasterAccess;

    /** Active documentation tab */
    public string $activeTab = 'overview';

    public function boot(): void
    {
        $this->assertMasterAccess();
    }

    public function render()
    {
        return view('ptah::livewire.permission.permission-guide');
    }
}
