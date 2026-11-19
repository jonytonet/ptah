<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\BaseCrud\CrudConfig;

/** CrudConfig (the BaseCrud's visual config editor), run without Livewire by the crud-config API (1.59.0). The logic is the parent's. */
class HeadlessCrudConfig extends CrudConfig
{
    use RunsHeadless;
}
