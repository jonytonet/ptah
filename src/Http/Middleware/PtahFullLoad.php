<?php

declare(strict_types=1);

namespace Ptah\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Marks a route as "always a document load". Registered as 'ptah.full-load'.
 *
 * It does nothing to the request. It is read by Ptah\Support\SpaNavigation:
 * with the Livewire layout's single-page navigation on, a link of the layout
 * to a route that carries this middleware is printed WITHOUT the navigate
 * attribute, so the browser loads the page — instead of ptah fetching it,
 * finding out it is not the layout and loading it again (two requests).
 *
 * For screens with a layout of their own: a point of sale, a TV panel, a
 * print view.
 *
 * Usage:
 *   Route::get('pdv', PdvController::class)->middleware('ptah.full-load');
 */
class PtahFullLoad
{
    public const ALIAS = 'ptah.full-load';

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
