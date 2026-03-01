<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('ptah::layouts.forge-dashboard')]
class PermissionGuide extends Component
{
    /** Aba ativa da documentação */
    public string $activeTab = 'overview';

    public function render()
    {
        return view('ptah::livewire.permission.permission-guide');
    }
}
