<?php

declare(strict_types=1);

namespace Ptah\Livewire\Concerns;

/**
 * Re-validates, on every component request, that the user may administer the
 * application's STRUCTURE — its menu and its companies.
 *
 * `/ptah-menu` and `/ptah-companies` were behind `['web', 'auth']` and nothing
 * else, and the components had no check of their own: any authenticated user
 * could create, edit and delete companies and menu items. Worse, a menu item's
 * URL was validated only as `string|max:2048` and rendered into the sidebar as
 * `href="{{ $itemUrl }}"` — escaping does not neutralise a scheme — so an
 * ordinary user could store `javascript:…` and wait for a master to click it.
 * (That second half is closed independently by Ptah\Support\SafeUrl, for every
 * host, whoever may edit.)
 *
 * Called from `boot()`, which Livewire runs on the initial mount AND on every
 * later action. A route middleware alone would not do: Livewire 4 does not
 * reapply custom route middleware to the update requests a mounted component
 * sends (see RequiresMasterAccess, which does the same for the ACL screens).
 */
trait RequiresStructureAccess
{
    protected function assertStructureAccess(): void
    {
        abort_unless(
            function_exists('ptah_can_manage_structure') && ptah_can_manage_structure(),
            403,
            trans('ptah::ui.permission_denied')
        );
    }
}
