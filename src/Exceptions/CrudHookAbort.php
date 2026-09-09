<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Throwable;

/**
 * A lifecycle hook refusing the save.
 *
 * `executeDynamicHook()` logs and continues by design — a hook that sends a
 * notification, or writes an audit line, must not take the whole form down
 * with it. But the same swallow applies to a hook that is a BARRIER, and there
 * the silence is the defect:
 *
 *   a `beforeUpdate` that stops a password hash from being re-hashed
 *   a `beforeCreate` that generates a temporary password
 *
 * If the first fails, the record saves without its guard. If the second fails,
 * the record saves with an EMPTY password. Both write the row and report
 * success, with only a line in the log.
 *
 * This exception is the explicit way to say "this failure must stop the save":
 * it is never swallowed, whatever the hook is. Two other things also propagate
 * — a `ValidationException`, which is Laravel's canonical way to reject input,
 * and any throwable from a hook the config declares critical:
 *
 *     "lifecycleHooksCritical": { "beforeCreate": true }
 *
 * ── What abort means for each half of the cycle ──────────────────────────
 *
 * A `before*` hook runs before anything is written, so aborting there leaves
 * nothing behind. An `after*` hook runs after the row is committed, and there
 * is no transaction around the save — so an abort reports the failure but the
 * record STAYS. The `persisted` context says which happened, and the message
 * the user sees is different for the two, because "error saving" would be a
 * lie about a record that exists.
 */
class CrudHookAbort extends PtahException
{
    /**
     * Wrap another failure as an abort, keeping it as the cause.
     */
    public static function from(Throwable $previous, string $hook, bool $persisted): self
    {
        return new self(
            $previous->getMessage(),
            0,
            $previous,
            ['hook' => $hook, 'persisted' => $persisted]
        );
    }

    /**
     * Was the record already written when this abort happened?
     */
    public function persisted(): bool
    {
        return (bool) ($this->getContext()['persisted'] ?? false);
    }
}
