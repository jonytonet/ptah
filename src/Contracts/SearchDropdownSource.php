<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * The only class a service-mode searchdropdown (`colsSDTipo: service`) may
 * call (1.49.0).
 *
 * Before 1.49.0 the config named a class AND a method, and ptah called
 * `app($class)->{$method}($term)` for any class and any public method, with
 * the term the user typed — whoever could edit a screen config could point
 * it at any method of the application or of vendor/. Now the class must
 * implement this interface, and the method segment of the config is only a
 * KEY handed to it: one service can serve several dropdowns and dispatch on
 * the key itself, but ptah never invokes a name taken from configuration.
 *
 *   colsSDModel "Erp\BranchService\active"  →  BranchService::searchDropdown($term, 'active')
 */
interface SearchDropdownSource
{
    /**
     * @param  string  $query  what the user typed ('' for the initial list)
     * @param  string  $key  the method segment of the config — a key, never called
     * @return list<array{value: mixed, label: string}>
     */
    public function searchDropdown(string $query, string $key): array;
}
