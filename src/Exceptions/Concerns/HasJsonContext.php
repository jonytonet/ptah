<?php

declare(strict_types=1);

namespace Ptah\Exceptions\Concerns;

/**
 * Trait for exceptions that involve JSON configuration errors.
 *
 * Provides methods to attach JSON path, line number, and expected values
 * to exception context for detailed error reporting.
 *
 * @package Ptah\Exceptions\Concerns
 */
trait HasJsonContext
{
    /**
     * Set the JSON path where the error occurred (e.g., "$.cols[2].colsMask").
     *
     * @param string $path
     * @return $this
     */
    public function withJsonPath(string $path): static
    {
        return $this->setContext('json_path', $path);
    }

    /**
     * Set the line number in the JSON where the error occurred.
     *
     * @param int $lineNumber
     * @return $this
     */
    public function withLineNumber(int $lineNumber): static
    {
        return $this->setContext('line_number', $lineNumber);
    }

    /**
     * Set the field name that caused the error.
     *
     * @param string $field
     * @return $this
     */
    public function withField(string $field): static
    {
        return $this->setContext('field', $field);
    }

    /**
     * Set the actual value that was provided.
     *
     * @param mixed $value
     * @return $this
     */
    public function withActualValue(mixed $value): static
    {
        return $this->setContext('actual_value', $value);
    }

    /**
     * Set the expected value or type.
     *
     * @param mixed $value
     * @return $this
     */
    public function withExpectedValue(mixed $value): static
    {
        return $this->setContext('expected_value', $value);
    }

    /**
     * Set the expected type.
     *
     * @param string $type
     * @return $this
     */
    public function withExpectedType(string $type): static
    {
        return $this->setContext('expected_type', $type);
    }

    /**
     * Set the section of the configuration (e.g., "cols", "actions", "filters").
     *
     * @param string $section
     * @return $this
     */
    public function withSection(string $section): static
    {
        return $this->setContext('section', $section);
    }

    /**
     * Set the model class name.
     *
     * @param string $model
     * @return $this
     */
    public function withModel(string $model): static
    {
        return $this->setContext('model', $model);
    }

    /**
     * Set available/valid options.
     *
     * @param array<int|string, mixed> $options
     * @return $this
     */
    public function withAvailableOptions(array $options): static
    {
        return $this->setContext('available_options', $options);
    }

    /**
     * Set a suggestion for how to fix the error.
     *
     * @param string $suggestion
     * @return $this
     */
    public function withSuggestion(string $suggestion): static
    {
        return $this->setContext('suggestion', $suggestion);
    }

    /**
     * Get the JSON path from context.
     *
     * @return string|null
     */
    public function getJsonPath(): ?string
    {
        return $this->context['json_path'] ?? null;
    }

    /**
     * Get the line number from context.
     *
     * @return int|null
     */
    public function getLineNumber(): ?int
    {
        return $this->context['line_number'] ?? null;
    }

    /**
     * Get the field from context.
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->context['field'] ?? null;
    }

    /**
     * Get the actual value from context.
     *
     * @return mixed
     */
    public function getActualValue(): mixed
    {
        return $this->context['actual_value'] ?? null;
    }

    /**
     * Get the expected value from context.
     *
     * @return mixed
     */
    public function getExpectedValue(): mixed
    {
        return $this->context['expected_value'] ?? null;
    }

    /**
     * Get the section from context.
     *
     * @return string|null
     */
    public function getSection(): ?string
    {
        return $this->context['section'] ?? null;
    }

    /**
     * Get the model from context.
     *
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->context['model'] ?? null;
    }
}
