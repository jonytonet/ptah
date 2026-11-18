<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Ptah\Crud\CrudScreenRegistry;
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
