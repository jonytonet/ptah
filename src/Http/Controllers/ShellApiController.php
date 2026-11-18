<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Services\DashboardService;
use Ptah\Support\MenuResolver;
use Ptah\Support\ProfilePhoto;
use Ptah\Support\StackPage;

/**
 * What a client's layout needs around the CRUD screens (1.59.0): the menu —
 * the very tree the Livewire sidebar draws — and who is signed in.
 */
class ShellApiController extends Controller
{
    /**
     * Everything the sidebar draws: the tree, the flat list of links the
     * "jump to a screen" search runs over (with the trail to each one, and the
     * text already folded — the same MenuResolver the Livewire sidebar and the
     * AI's find_menu tool use), whether that search is shown, and the brand.
     */
    public function menu(): JsonResponse
    {
        $flat = MenuResolver::flatLinks();
        $nested = false;

        foreach ($flat as $link) {
            if (count($link['breadcrumb']) > 1) {
                $nested = true;
                break;
            }
        }

        // The rule of forge-sidebar.blade.php: never under 3 links; with many
        // links, or as soon as one sits inside a group.
        $min = (int) config('ptah.forge.sidebar_jump_min_items', 8);
        $jump = config('ptah.forge.sidebar_jump', true)
            && count($flat) >= 3
            && (count($flat) >= max(2, $min) || $nested);

        return response()->json([
            'data' => MenuResolver::tree(),
            'flat' => $flat,
            'jump' => (bool) $jump,
            'brand' => [
                'name' => (string) config('app.name', 'Ptah'),
                'logo' => self::asset(config('ptah.brand.logo')),
                'logo_dark' => self::asset(config('ptah.brand.logo_dark')),
            ],
            // The top bar: company switcher, bell, administration links.
            'shell' => StackPage::shell(),
        ]);
    }

    /** A URL as it is; a path under public/ as its asset URL. */
    private static function asset(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return preg_match('#^(https?:)?//|^/|^data:#i', $value) === 1 ? $value : asset($value);
    }

    /**
     * The widgets of config/ptah-dashboard.php this user may see, computed —
     * what ptah::dashboard.widgets draws. `group` names a group of widgets.
     */
    public function dashboard(Request $request, DashboardService $dashboard): JsonResponse
    {
        $group = $request->query('group');

        return response()->json(['data' => $dashboard->visibleWidgets(is_string($group) && $group !== '' ? $group : null)]);
    }

    public function me(Request $request, CrudScreenRegistry $screens): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user === null ? null : [
                'id' => $user->getAuthIdentifier(),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'photo_url' => ProfilePhoto::url($user),
            ],
            'company_id' => function_exists('ptah_company_id') ? ptah_company_id() : null,
            'locale' => app()->getLocale(),
            'screens' => $screens->keys(),
        ]);
    }
}
