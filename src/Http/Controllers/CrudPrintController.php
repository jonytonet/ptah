<?php

declare(strict_types=1);

namespace Ptah\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Renders the BaseCrud print screen.
 *
 * The heavy lifting (filtering, cell rendering, totals) is done by the BaseCrud
 * Livewire component, which caches a ready-to-display payload under a one-time
 * token. This controller only fetches that payload and returns a clean HTML view
 * — so it can never diverge from what the listing shows.
 */
class CrudPrintController
{
    public function print(string $token): View
    {
        $payload = Cache::get('ptah:print:'.$token);

        if (! is_array($payload)) {
            // Expired or unknown token — nothing to print.
            abort(404);
        }

        // The snapshot is bound to the user who generated it. A null userId means
        // the listing was public (no auth); only then is an anonymous view allowed.
        $owner = $payload['userId'] ?? null;
        if ($owner !== null && $owner !== Auth::id()) {
            abort(403);
        }

        return view('ptah::livewire.base-crud.crud-print', [
            'title' => $payload['title'] ?? '',
            'columns' => $payload['columns'] ?? [],
            'rows' => $payload['rows'] ?? [],
            'filters' => $payload['filters'] ?? [],
            'totalRecords' => $payload['totalRecords'] ?? 0,
            'truncated' => $payload['truncated'] ?? false,
            'maxRows' => $payload['maxRows'] ?? 0,
            'generatedAt' => $payload['generatedAt'] ?? '',
            'hasTotals' => collect($payload['columns'] ?? [])->contains(fn ($c) => ($c['total'] ?? null) !== null),
        ]);
    }
}
