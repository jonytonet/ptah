<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\Menu\MenuList;

/** MenuList, run without Livewire by the menu admin API. The logic is the parent's. */
class HeadlessMenuList extends MenuList
{
    use RunsHeadless {
        validate as private headlessValidate;
    }

    /**
     * Livewire reads the component's `$messages` when save() calls validate()
     * with no arguments; RunsHeadless does not, so the custom messages ("The
     * menu text is required.") are handed over here to keep the same wording.
     */
    public function validate($rules = null, $messages = [], $attributes = [])
    {
        return $this->headlessValidate($rules, $messages ?: $this->messages, $attributes);
    }
}
