<?php

declare(strict_types=1);

namespace Ptah\Http\Headless;

use Ptah\Crud\RunsHeadless;
use Ptah\Livewire\AI\AiModelConfigList;

/** AiModelConfigList, run without Livewire by the AI admin API (1.59.0). The logic is the parent's. */
class HeadlessAiModelConfigList extends AiModelConfigList
{
    use RunsHeadless;
}
