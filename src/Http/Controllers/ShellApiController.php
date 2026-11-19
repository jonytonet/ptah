<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Services\DashboardService;
use Ptah\Support\StackBoot;

/**
 * What a client's layout needs around the CRUD screens (1.59.0): the menu —
 * the very tree the Livewire sidebar draws — and who is signed in. Built by
 * StackBoot, which the shell page also embeds (`data-boot`).
 */
class ShellApiController extends Controller
{
    /**
     * Everything the sidebar draws: the tree, the flat list for the "jump to
     * a screen" search, whether that search is shown, the brand and the top
     * bar (see StackBoot::menu()).
     */
    public function menu(): JsonResponse
    {
        return response()->json(StackBoot::menu());
    }

    /**
     * The widgets of config/ptah-dashboard.php this user may see, computed —
     * what ptah::dashboard.widgets draws. `group` names a group of widgets.
     */
    public function dashboard(Request $request, DashboardService $dashboard): JsonResponse
    {
        $group = is_string($request->query('group')) && $request->query('group') !== '' ? (string) $request->query('group') : null;

        return response()->json([
            'data' => $dashboard->visibleWidgets($group),
            // Without widgets, livewire/auth/dashboard draws four cards of its
            // own: the user, the system, the environment and Laravel's version.
            'fallback' => $group === null && ! $dashboard->hasWidgets() ? [
                'app' => (string) config('app.name'),
                'environment' => ucfirst((string) config('app.env')),
                'laravel' => app()->version(),
            ] : null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(StackBoot::me($request->user()));
    }
}
