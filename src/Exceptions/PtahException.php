<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for all Ptah framework exceptions.
 *
 * Provides structured error context and formatting capabilities.
 * All custom Ptah exceptions should extend this class.
 *
 * @package Ptah\Exceptions
 */
abstract class PtahException extends Exception
{
    /**
     * Additional context data for the exception.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Create a new Ptah exception instance.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Create a new exception instance with context.
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return static
     */
    public static function withContext(string $message, array $context = []): static
    {
        return new static($message, 0, null, $context);
    }

    /**
     * Get the exception context.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context data.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Merge additional context data.
     *
     * @param array<string, mixed> $context
     * @return $this
     */
    public function mergeContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Add a suggestion to help fix the error.
     *
     * @param string $suggestion
     * @return $this
     */
    public function withSuggestion(string $suggestion): static
    {
        $this->context['suggestion'] = $suggestion;

        return $this;
    }

    /**
     * Get formatted message with context for display.
     *
     * @return string
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if (empty($this->context)) {
            return $message;
        }

        $contextLines = [];
        foreach ($this->context as $key => $value) {
            $contextLines[] = sprintf('%s: %s', ucfirst($key), $this->formatValue($value));
        }

        return $message . "\n" . implode("\n", $contextLines);
    }

    /**
     * Format a context value for display.
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_object($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : get_class($value);
        }

        return (string) $value;
    }

    /**
     * Convert exception to array format (useful for logging/monitoring).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Convert exception to JSON format (useful for API responses).
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
