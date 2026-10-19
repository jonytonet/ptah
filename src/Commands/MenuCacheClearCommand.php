<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Models\Menu;

/**
 * `ptah:menu-cache-clear` — drops the cached sidebar tree (1.46.5).
 *
 * A change made to the `menus` table outside the model — raw SQL, another
 * application — keeps showing the old menu until the cache expires. Changes
 * through the model, the menu editor and `ptah:menu-sync` clear it already.
 */
class MenuCacheClearCommand extends Command
{
    protected $signature = 'ptah:menu-cache-clear';

    protected $description = 'Clear the cached sidebar menu tree (after changing the menus table by hand)';

    public function handle(): int
    {
        Menu::clearCache();
        $this->components->info('Menu cache cleared.');

        return self::SUCCESS;
    }
}
