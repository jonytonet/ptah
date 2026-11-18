<?php

declare(strict_types=1);

namespace Ptah\Support;

use Closure;
use Illuminate\Support\Facades\Route;
use Ptah\Generators\ViewGenerator;

/**
 * Which page answers a ptah URL (1.59.0): the Livewire component, or — in
 * the React/Vue stack with the API on — the stack page (ptah::stack.app)
 * that draws the same screen over the API. The URL and the route name are
 * the same in every stack, so a menu or a `route()` call never changes.
 */
final class StackPage
{
    public static function active(): bool
    {
        return ViewGenerator::usesAppStack() && (bool) config('ptah.crud_api.enabled');
    }

    /**
     * The route action: `$livewire` as it is, or the stack page named
     * `$page`. `$guard` names the lock the Livewire component applies in
     * boot() — `structure` — so the page itself answers 403, not only the
     * API behind it.
     *
     * @param  class-string  $livewire
     * @return class-string|Closure
     */
    public static function action(string $page, string $livewire, ?string $guard = null): string|Closure
    {
        if (! self::active()) {
            return $livewire;
        }

        return function () use ($page, $guard) {
            if ($guard === 'structure') {
                abort_unless(
                    function_exists('ptah_can_manage_structure') && ptah_can_manage_structure(),
                    403,
                    trans('ptah::ui.permission_denied')
                );
            }

            return view('ptah::stack.app', ['page' => $page]);
        };
    }

    /**
     * What the React/Vue top bar offers this user, by the rules of
     * forge-navbar.blade.php: the company switcher, the bell, and each
     * administration link only for who passes the lock of the screen it opens.
     *
     * @return array{company_switcher: bool, notifications: bool, admin: list<array{label: string, url: string, icon: string}>}
     */
    public static function shell(): array
    {
        $auth = auth()->check();
        $structure = $auth && function_exists('ptah_can_manage_structure') && ptah_can_manage_structure();
        $master = $auth && config('ptah.modules.permissions') && function_exists('ptah_is_master') && ptah_is_master();
        $ai = $auth && config('ptah.modules.ai_agent')
            && ((function_exists('ptah_can') && ptah_can('ai.config', 'read')) || (function_exists('ptah_is_master') && ptah_is_master()));

        $candidates = [
            ['ptah.company.index', 'navbar_admin_company', 'bx bx-buildings', $structure && config('ptah.modules.company')],
            ['ptah.acl.departments', 'navbar_admin_departments', 'bx bx-sitemap', $master],
            ['ptah.acl.roles', 'navbar_admin_roles', 'bx bx-shield-quarter', $master],
            ['ptah.acl.pages', 'navbar_admin_pages', 'bx bx-layer', $master],
            ['ptah.acl.users', 'navbar_admin_users', 'bx bx-group', $master],
            ['ptah.acl.audit', 'navbar_admin_audit', 'bx bx-history', $master],
            ['ptah.acl.guide', 'navbar_admin_guide', 'bx bx-book-open', $master],
            ['ptah.menu.manage', 'navbar_admin_menu', 'bx bx-menu', $structure && config('ptah.modules.menu')],
            ['ptah.ai.models', 'navbar_admin_ai_models', 'bx bx-bot', $ai],
        ];

        $admin = [];
        foreach ($candidates as [$route, $label, $icon, $allowed]) {
            if ($allowed && Route::has($route)) {
                $admin[] = ['label' => (string) trans('ptah::ui.'.$label), 'url' => route($route), 'icon' => $icon];
            }
        }

        return [
            'company_switcher' => $auth && (bool) config('ptah.modules.company'),
            'notifications' => $auth && (bool) config('ptah.notifications.enabled'),
            'admin' => $admin,
        ];
    }
}
