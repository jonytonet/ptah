<?php

declare(strict_types=1);

namespace Ptah\Services\Validation\Formatters;

use Ptah\Exceptions\PtahException;
use Illuminate\Support\Str;

/**
 * Formats exceptions for CLI output with box drawing characters.
 *
 * @package Ptah\Services\Validation\Formatters
 */
class CliErrorFormatter
{
    /**
     * Format an exception for CLI display.
     *
     * @param PtahException $exception
     * @param int $maxWidth
     * @return string
     */
    public function format(PtahException $exception, int $maxWidth = 70): string
    {
        if (method_exists($exception, 'formatAsCliOutput')) {
            return $exception->formatAsCliOutput();
        }

        return $this->defaultFormat($exception, $maxWidth);
    }

    /**
     * Default formatting for exceptions without custom formatter.
     *
     * @param PtahException $exception
     * @param int $maxWidth
     * @return string
     */
    protected function defaultFormat(PtahException $exception, int $maxWidth): string
    {
        $lines = [];

        // Top border
        $lines[] = '╔' . str_repeat('═', $maxWidth - 2) . '╗';

        // Title with error emoji
        $title = '❌ ' . $this->getErrorTitle($exception);
        $lines[] = '║ ' . str_pad($title, $maxWidth - 4) . ' ║';

        // Message
        if ($exception->getMessage()) {
            $lines[] = '╠' . str_repeat('═', $maxWidth - 2) . '╣';
            $messageLines = $this->wrapText($exception->getMessage(), $maxWidth - 4);
            foreach ($messageLines as $line) {
                $lines[] = '║ ' . str_pad($line, $maxWidth - 4) . ' ║';
            }
        }

        // Context
        $context = $exception->getContext();
        if (!empty($context)) {
            $lines[] = '╠' . str_repeat('═', $maxWidth - 2) . '╣';

            foreach ($this->formatContext($context) as $key => $value) {
                $contextLine = sprintf('%s: %s', $key, $value);
                $wrappedLines = $this->wrapText($contextLine, $maxWidth - 4);
                foreach ($wrappedLines as $line) {
                    $lines[] = '║ ' . str_pad($line, $maxWidth - 4) . ' ║';
                }
            }
        }

        // Bottom border
        $lines[] = '╚' . str_repeat('═', $maxWidth - 2) . '╝';

        return "\n" . implode("\n", $lines) . "\n";
    }

    /**
     * Get a human-readable error title from the exception class name.
     *
     * @param PtahException $exception
     * @return string
     */
    protected function getErrorTitle(PtahException $exception): string
    {
        $className = class_basename($exception);

        return Str::headline($className);
    }

    /**
     * Format context array with localized labels.
     *
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    protected function formatContext(array $context): array
    {
        $formatted = [];

        $labels = [
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
            'command' => 'Comando',
            'option' => 'Opção',
            'argument' => 'Argumento',
        ];

        foreach ($context as $key => $value) {
            $label = $labels[$key] ?? Str::headline($key);
            $formatted[$label] = $this->formatValue($value);
        }

        return $formatted;
    }

    /**
     * Format a value for display.
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn($v) => $this->formatValue($v), $value));
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
     * Wrap text to fit within specified width.
     *
     * @param string $text
     * @param int $width
     * @return array<int, string>
     */
    protected function wrapText(string $text, int $width): array
    {
        if (mb_strlen($text) <= $width) {
            return [$text];
        }

        $lines = [];
        $words = explode(' ', $text);
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine ? $currentLine . ' ' . $word : $word;

            if (mb_strlen($testLine) <= $width) {
                $currentLine = $testLine;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;

                // If single word is too long, break it
                if (mb_strlen($currentLine) > $width) {
                    $lines[] = mb_substr($currentLine, 0, $width);
                    $currentLine = mb_substr($currentLine, $width);
                }
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines ?: [''];
    }

    /**
     * Format multiple exceptions as a list.
     *
     * @param array<int, PtahException> $exceptions
     * @param int $maxWidth
     * @return string
     */
    public function formatMultiple(array $exceptions, int $maxWidth = 70): string
    {
        $output = [];

        $output[] = '╔' . str_repeat('═', $maxWidth - 2) . '╗';
        $output[] = '║ ' . str_pad('⚠️  Multiple Errors Detected', $maxWidth - 4) . ' ║';
        $output[] = '╚' . str_repeat('═', $maxWidth - 2) . '╝';
        $output[] = '';

        foreach ($exceptions as $index => $exception) {
            $output[] = sprintf('Error #%d:', $index + 1);
            $output[] = $this->format($exception, $maxWidth);
        }

        return implode("\n", $output);
    }
}
