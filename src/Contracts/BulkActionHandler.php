<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * The only class a configured bulk action (`bulkActions[].method`) may call
 * (1.49.0).
 *
 * Before 1.49.0 `"method": "Class@method"` made ptah call
 * `app($class)->{$method}($ids, $model)` for any class and any public method
 * — whoever could edit a screen config chose what ran. Now the class must
 * implement this interface, and the part after `@` is only a KEY handed to
 * it: one handler can serve several actions, but ptah never invokes a name
 * taken from configuration.
 *
 *   "method": "App\Services\OrderBulk@approve"  →  OrderBulk::handleBulkAction($ids, $model, 'approve')
 *
 * `$ids` are already re-scoped to the screen (company / master-detail lock).
 */
interface BulkActionHandler
{
    /**
     * @param  list<int|string>  $ids  scoped ids of the selected rows
     * @param  string  $model  the screen's model key
     * @param  string  $key  the part after `@` in the config — a key, never called
     */
    public function handleBulkAction(array $ids, string $model, string $key): void;
}
