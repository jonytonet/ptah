<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Crud\CrudScreenRegistry;
use Ptah\Services\DashboardService;
use Ptah\Support\MenuResolver;

/**
 * What a client's layout needs around the CRUD screens (1.59.0): the menu —
 * the very tree the Livewire sidebar draws — and who is signed in.
 */
class ShellApiController extends Controller
{
    public function menu(): JsonResponse
    {
        return response()->json(['data' => MenuResolver::tree()]);
    }

    /**
     * The widgets of config/ptah-dashboard.php this user may see, computed —
     * what ptah::dashboard.widgets draws. `group` names a group of widgets.
     */
    public function dashboard(Request $request, DashboardService $dashboard): JsonResponse
    {
        $group = $request->query('group');

        return response()->json(['data' => array_values($dashboard->visibleWidgets(is_string($group) && $group !== '' ? $group : null))]);
    }

    public function me(Request $request, CrudScreenRegistry $screens): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user === null ? null : [
                'id' => $user->getAuthIdentifier(),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
            ],
            'company_id' => function_exists('ptah_company_id') ? ptah_company_id() : null,
            'locale' => app()->getLocale(),
            'screens' => $screens->keys(),
        ]);
    }
}
