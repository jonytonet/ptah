<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Permission\PermissionGuide;

/** PermissionGuide, run without Livewire by the roles API (1.59.0). Booting it runs its MASTER guard. */
class HeadlessPermissionGuide extends PermissionGuide
{
    use RunsHeadless;
}
