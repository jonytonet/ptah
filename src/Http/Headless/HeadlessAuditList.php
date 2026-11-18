<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Permission\AuditList;

/** AuditList, run without Livewire by the access API. The logic is the parent's. */
class HeadlessAuditList extends AuditList
{
    use RunsHeadless;
}
