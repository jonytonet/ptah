<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Permission\UserPermissionList;

/** UserPermissionList, run without Livewire by the access API. The logic is the parent's. */
class HeadlessUserPermissionList extends UserPermissionList
{
    use RunsHeadless;
}
