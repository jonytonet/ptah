<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Ptah\Exceptions\Concerns\FormatsError;

/**
 * Exception thrown when business rules are violated.
 *
 * This exception is thrown when operations violate domain-specific
 * business rules (e.g., deactivating a master role, deleting a resource in use).
 */
class BusinessRuleException extends PtahException
{
    use FormatsError;

    /**
     * Create a new BusinessRuleException for protected resource.
     */
    public static function resourceProtected(string $resource, string $reason): static
    {
        $message = sprintf('Cannot modify "%s": %s', $resource, $reason);

        return static::withContext($message, [
            'resource' => $resource,
            'reason' => $reason,
        ]);
    }

    /**
     * Create a new BusinessRuleException for resource in use.
     */
    public static function resourceInUse(string $resource, string $usedBy): static
    {
        $message = sprintf('Cannot delete "%s" because it is in use by %s', $resource, $usedBy);

        return static::withContext($message, [
            'resource' => $resource,
            'used_by' => $usedBy,
        ]);
    }

    /**
     * Create a new BusinessRuleException for duplicate resource.
     */
    public static function duplicateResource(string $resource, string $field, mixed $value): static
    {
        $message = sprintf(
            'A %s with %s "%s" already exists',
            $resource,
            $field,
            (string) $value
        );

        return static::withContext($message, [
            'resource' => $resource,
            'field' => $field,
            'value' => $value,
        ]);
    }

    /**
     * Create a new BusinessRuleException for insufficient permissions.
     */
    public static function insufficientPermissions(string $action, string $resource): static
    {
        $message = sprintf('You do not have permission to %s %s', $action, $resource);

        return static::withContext($message, [
            'action' => $action,
            'resource' => $resource,
        ]);
    }

    /**
     * Create a new BusinessRuleException for invalid state transition.
     */
    public static function invalidStateTransition(
        string $resource,
        string $currentState,
        string $targetState
    ): static {
        $message = sprintf(
            'Cannot transition %s from "%s" to "%s"',
            $resource,
            $currentState,
            $targetState
        );

        return static::withContext($message, [
            'resource' => $resource,
            'current_state' => $currentState,
            'target_state' => $targetState,
        ]);
    }

    /**
     * Get HTTP status code for API responses.
     */
    protected function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }
}
