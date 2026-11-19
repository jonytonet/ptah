<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Notification\NotificationBell;

/** NotificationBell, run without Livewire by the shell API (1.59.0). The logic is the parent's. */
class HeadlessNotificationBell extends NotificationBell
{
    use RunsHeadless;
}
