<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Company\CompanyList;

/** CompanyList, run without Livewire by the company admin API (1.59.0). The logic is the parent's. */
class HeadlessCompanyList extends CompanyList
{
    use RunsHeadless;
}
