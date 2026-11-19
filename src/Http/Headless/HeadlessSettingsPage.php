<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Settings\SettingsPage;

/** SettingsPage, run without Livewire by the settings admin API. The logic is the parent's. */
class HeadlessSettingsPage extends SettingsPage
{
    use RunsHeadless;
}
