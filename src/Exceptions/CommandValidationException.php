<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Ptah\Exceptions\Concerns\FormatsError;

/**
 * Exception thrown when command input validation fails.
 *
 * This exception is thrown when command-line arguments or options
 * provided to Artisan commands are invalid or missing.
 */
class CommandValidationException extends PtahException
{
    use FormatsError;

    /**
     * Create a new CommandValidationException for missing required argument.
     */
    public static function missingArgument(string $argument, string $command): static
    {
        $message = sprintf('Required argument "%s" is missing for command "%s"', $argument, $command);

        return static::withContext($message, [
            'argument' => $argument,
            'command' => $command,
        ]);
    }

    /**
     * Create a new CommandValidationException for invalid option format.
     */
    public static function invalidOptionFormat(
        string $option,
        string $value,
        string $expectedFormat
    ): static {
        $message = sprintf(
            'Invalid format for option "--%s=%s". Expected format: %s',
            $option,
            $value,
            $expectedFormat
        );

        return static::withContext($message, [
            'option' => $option,
            'actual_value' => $value,
            'expected_format' => $expectedFormat,
        ]);
    }

    /**
     * Create a new CommandValidationException for invalid option value.
     *
     * @param  array<int, string>  $validValues
     */
    public static function invalidOptionValue(
        string $option,
        string $value,
        array $validValues
    ): static {
        $message = sprintf(
            'Invalid value "%s" for option "--%s". Valid values: %s',
            $value,
            $option,
            implode(', ', $validValues)
        );

        return static::withContext($message, [
            'option' => $option,
            'actual_value' => $value,
            'available_options' => $validValues,
        ]);
    }

    /**
     * Create a new CommandValidationException for conflicting options.
     */
    public static function conflictingOptions(string $option1, string $option2): static
    {
        $message = sprintf(
            'Options "--%s" and "--%s" cannot be used together',
            $option1,
            $option2
        );

        return static::withContext($message, [
            'conflicting_options' => [$option1, $option2],
        ]);
    }

    /**
     * Create a new CommandValidationException for model not found.
     */
    public static function modelNotFound(string $model): static
    {
        $message = sprintf('Model class "%s" not found', $model);

        return static::withContext($message, [
            'model' => $model,
            'suggestion' => 'Verify the fully qualified class name (e.g., App\Models\Product)',
        ]);
    }

    /**
     * Get HTTP status code for API responses.
     */
    protected function getHttpStatusCode(): int
    {
        return 400; // Bad Request
    }
}
