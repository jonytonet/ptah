<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Permission\PageList;

/** PageList, run without Livewire by the roles API (1.59.0). The logic is the parent's. */
class HeadlessPageList extends PageList
{
    use RunsHeadless;
}
