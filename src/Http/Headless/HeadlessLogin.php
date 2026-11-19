<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Auth\LoginPage;

/** LoginPage, run without Livewire by the auth API (1.59.0). The logic is the parent's. */
class HeadlessLogin extends LoginPage
{
    use RunsHeadless;
}
