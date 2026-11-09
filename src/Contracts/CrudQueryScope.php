<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A host-side restriction of what a BaseCrud may see and touch (1.58.0).
 *
 * `lockedFilters` only locks equality and `whereHasFilter` one condition on
 * one relation. A screen that must lock a range (the day, in the user's
 * timezone) or a composed scope (partner → credentials → connectors) passes a
 * scope instead:
 *
 *   Blade: @livewire('ptah-base-crud', [
 *       'model'       => 'Integration/ConnectorRun',
 *       'scope'       => \App\CrudScopes\HistoryScope::class,
 *       'scopeParams' => ['day' => '2026-11-09', 'partner' => 12],
 *   ], key($filterKey))
 *
 * Applied wherever `lockedFilters` is — the listing (and with it the totals,
 * print and both exports), and the single-record actions: edit, duplicate,
 * save, delete, restore, bulk actions. The user cannot undo it. Both values are
 * #[Locked] and only reach the component through `mount()`, i.e. host code.
 *
 * Only RESTRICT the query (where/whereIn/whereHas/whereBetween…). ptah calls
 * apply() inside a `where(...)` group, so an `orWhere` cannot OR past the
 * company scope and the locks applied before it — but it still widens the
 * scope itself.
 */
interface CrudQueryScope
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $params  the `scopeParams` given to mount()
     */
    public function apply(Builder $query, array $params): void;
}
