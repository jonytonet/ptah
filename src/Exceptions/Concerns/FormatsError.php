<?php

declare(strict_types=1);

namespace Ptah\Exceptions\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for formatting exception messages for different outputs.
 *
 * Provides methods to format errors for CLI, flash messages, and API responses.
 *
 * @package Ptah\Exceptions\Concerns
 */
trait FormatsError
{
    /**
     * Format the exception for CLI output with box drawing characters.
     *
     * @return string
     */
    public function formatAsCliOutput(): string
    {
        $lines = [];
        $maxLength = 70;

        // Top border
        $lines[] = '╔' . str_repeat('═', $maxLength - 2) . '╗';

        // Title
        $title = '❌ ' . $this->getErrorTitle();
        $lines[] = '║ ' . str_pad($title, $maxLength - 4) . ' ║';

        // Middle separator if there's context
        if (!empty($this->context)) {
            $lines[] = '╠' . str_repeat('═', $maxLength - 2) . '╣';

            // Context lines
            foreach ($this->getContextForCli() as $key => $value) {
                $line = sprintf('%s: %s', $key, $value);
                // Wrap long lines
                $wrapped = $this->wrapLine($line, $maxLength - 4);
                foreach ($wrapped as $wrappedLine) {
                    $lines[] = '║ ' . str_pad($wrappedLine, $maxLength - 4) . ' ║';
                }
            }
        }

        // Bottom border
        $lines[] = '╚' . str_repeat('═', $maxLength - 2) . '╝';

        return "\n" . implode("\n", $lines) . "\n";
    }

    /**
     * Format the exception for flash message (HTML-safe).
     *
     * @return string
     */
    public function formatAsFlashMessage(): string
    {
        $html = '<div class="alert alert-danger">';
        $html .= '<strong>' . htmlspecialchars($this->getErrorTitle()) . '</strong>';

        if (!empty($this->context)) {
            $html .= '<ul class="mt-2 mb-0 list-unstyled">';
            foreach ($this->getContextForCli() as $key => $value) {
                $html .= sprintf(
                    '<li><strong>%s:</strong> %s</li>',
                    htmlspecialchars($key),
                    htmlspecialchars($value)
                );
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format the exception as JSON for API responses.
     *
     * Follows RFC 7807 Problem Details format.
     *
     * @return array<string, mixed>
     */
    public function formatAsJsonResponse(): array
    {
        return [
            'type' => $this->getErrorType(),
            'title' => $this->getErrorTitle(),
            'detail' => $this->getMessage(),
            'status' => $this->getHttpStatusCode(),
            'context' => $this->context,
        ];
    }

    /**
     * Get the error title (short description).
     *
     * @return string
     */
    protected function getErrorTitle(): string
    {
        // Extract class name without namespace
        $className = substr(strrchr(static::class, '\\'), 1);

        // Convert from PascalCase to Title Case with spaces
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $className);
    }

    /**
     * Get the error type identifier (for RFC 7807).
     *
     * @return string
     */
    protected function getErrorType(): string
    {
        return 'https://ptah.dev/errors/' . Str::kebab(class_basename(static::class));
    }

    /**
     * Get HTTP status code for API responses.
     *
     * @return int
     */
    protected function getHttpStatusCode(): int
    {
        // Default to 400 Bad Request for validation errors
        return 400;
    }

    /**
     * Get context formatted for CLI display.
     *
     * @return array<string, string>
     */
    protected function getContextForCli(): array
    {
        $formatted = [];

        $keyLabels = [
            'field' => 'Campo',
            'actual_value' => 'Valor atual',
            'expected_value' => 'Valor esperado',
            'expected_type' => 'Tipo esperado',
            'line_number' => 'Linha do JSON',
            'json_path' => 'Path JSON',
            'section' => 'Seção',
            'model' => 'Model',
            'available_options' => 'Opções válidas',
            'suggestion' => 'Sugestão',
        ];

        foreach ($this->context as $key => $value) {
            $label = $keyLabels[$key] ?? ucfirst($key);
            $formatted[$label] = $this->formatValue($value);
        }

        return $formatted;
    }

    /**
     * Wrap a long line to fit within the specified width.
     *
     * @param string $line
     * @param int $width
     * @return array<int, string>
     */
    protected function wrapLine(string $line, int $width): array
    {
        if (mb_strlen($line) <= $width) {
            return [$line];
        }

        $lines = [];
        $words = explode(' ', $line);
        $currentLine = '';

        foreach ($words as $word) {
            if (mb_strlen($currentLine . ' ' . $word) <= $width) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    /**
     * Format a value for display (must be defined in the using class).
     *
     * @param mixed $value
     * @return string
     */
    abstract protected function formatValue(mixed $value): string;
}
