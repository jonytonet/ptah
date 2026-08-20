<?php

declare(strict_types=1);

namespace Ptah\Livewire\Permission\Concerns;

/**
 * Re-validates MASTER access on every component request, not only the
 * initial page load.
 *
 * The ACL-management screens (roles, pages, users-ACL, audit, departments)
 * are reachable only behind the `ptah.master` route middleware (see
 * routes/ptah-permissions.php) — but Livewire 4 does not reapply custom route
 * middleware to the AJAX requests a mounted component makes afterwards (only
 * a small, framework-controlled allowlist is reapplied, see
 * PtahServiceProvider::registerLivewire()). Without this trait, a user whose
 * MASTER role is revoked mid-session — or a crafted Livewire update request
 * that targets an already-mounted component snapshot — could still call a
 * mutating method (save/delete/bind permissions/…) after the initial gate.
 *
 * Call assertMasterAccess() from boot(), which Livewire invokes on every
 * request for the component: the initial mount AND every subsequent action.
 */
trait RequiresMasterAccess
{
    protected function assertMasterAccess(): void
    {
        abort_unless(
            function_exists('ptah_is_master') && ptah_is_master(),
            403,
            trans('ptah::ui.permission_denied')
        );
    }
}
