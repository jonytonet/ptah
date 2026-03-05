<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Ptah\Exceptions\Concerns\FormatsError;
use Ptah\Exceptions\Concerns\HasJsonContext;

/**
 * Exception thrown when CRUD configuration validation fails.
 *
 * This exception is thrown when the JSON configuration for BaseCrud
 * contains invalid data, missing required fields, or fails schema validation.
 *
 * @package Ptah\Exceptions
 */
class ConfigValidationException extends PtahException
{
    use HasJsonContext;
    use FormatsError;

    /**
     * Create a new ConfigValidationException for an invalid column type.
     *
     * @param string $field
     * @param mixed $actualValue
     * @param array<int, string> $validTypes
     * @param string $section
     * @return static
     */
    public static function invalidColumnType(
        string $field,
        mixed $actualValue,
        array $validTypes,
        string $section = 'cols'
    ): static {
        $message = sprintf(
            'Invalid column type "%s" for field "%s". Valid types: %s',
            (string) $actualValue,
            $field,
            implode(', ', $validTypes)
        );

        return static::withContext($message)
            ->withField($field)
            ->withActualValue($actualValue)
            ->withAvailableOptions($validTypes)
            ->withSection($section);
    }

    /**
     * Create a new ConfigValidationException for a missing required field.
     *
     * @param string $field
     * @param string $section
     * @return static
     */
    public static function missingRequiredField(string $field, string $section): static
    {
        $message = sprintf('Required field "%s" is missing in section "%s"', $field, $section);

        return static::withContext($message)
            ->withField($field)
            ->withSection($section);
    }

    /**
     * Create a new ConfigValidationException for an invalid type.
     *
     * @param string $field
     * @param mixed $actualValue
     * @param string $expectedType
     * @param string $section
     * @return static
     */
    public static function invalidType(
        string $field,
        mixed $actualValue,
        string $expectedType,
        string $section
    ): static {
        $actualType = gettype($actualValue);
        $message = sprintf(
            'Field "%s" has invalid type %s, expected %s',
            $field,
            $actualType,
            $expectedType
        );

        return static::withContext($message)
            ->withField($field)
            ->withActualValue($actualValue)
            ->withExpectedType($expectedType)
            ->withSection($section);
    }

    /**
     * Create a new ConfigValidationException for missing dependencies.
     *
     * @param string $field
     * @param string $dependency
     * @param string $section
     * @return static
     */
    public static function missingDependency(
        string $field,
        string $dependency,
        string $section
    ): static {
        $message = sprintf(
            'Field "%s" requires "%s" to be configured',
            $field,
            $dependency
        );

        return static::withContext($message)
            ->withField($field)
            ->withSection($section)
            ->withSuggestion("Configure '{$dependency}' before using '{$field}'");
    }

    /**
     * Create a new ConfigValidationException for an invalid renderer configuration.
     *
     * @param string $renderer
     * @param string $missingConfig
     * @return static
     */
    public static function invalidRendererConfig(string $renderer, string $missingConfig): static
    {
        $message = sprintf(
            'Renderer "%s" requires configuration field "%s"',
            $renderer,
            $missingConfig
        );

        return static::withContext($message)
            ->withField('renderer')
            ->withActualValue($renderer)
            ->withSection('cols')
            ->withSuggestion("Add '{$missingConfig}' to column configuration");
    }

    /**
     * Create a new ConfigValidationException for invalid JOIN configuration.
     *
     * @param string $table
     * @param string $error
     * @return static
     */
    public static function invalidJoin(string $table, string $error): static
    {
        $message = sprintf('Invalid JOIN configuration for table "%s": %s', $table, $error);

        return static::withContext($message)
            ->withField('joins')
            ->withActualValue($table)
            ->withSection('joins');
    }

    /**
     * Create a new ConfigValidationException for duplicate configuration.
     *
     * @param string $field
     * @param mixed $value
     * @param string $section
     * @return static
     */
    public static function duplicateConfiguration(string $field, mixed $value, string $section): static
    {
        $message = sprintf('Duplicate configuration for "%s" with value "%s"', $field, (string) $value);

        return static::withContext($message)
            ->withField($field)
            ->withActualValue($value)
            ->withSection($section);
    }

    /**
     * Get HTTP status code for API responses.
     *
     * @return int
     */
    protected function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }
}
