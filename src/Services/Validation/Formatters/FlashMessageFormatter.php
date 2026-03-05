<?php

declare(strict_types=1);

namespace Ptah\Services\Validation\Formatters;

use Ptah\Exceptions\PtahException;
use Illuminate\Support\Str;

/**
 * Formats exceptions for flash messages (HTML output).
 *
 * @package Ptah\Services\Validation\Formatters
 */
class FlashMessageFormatter
{
    /**
     * Format an exception for flash message display.
     *
     * @param PtahException $exception
     * @param string $alertClass
     * @return string
     */
    public function format(PtahException $exception, string $alertClass = 'alert-danger'): string
    {
        if (method_exists($exception, 'formatAsFlashMessage')) {
            return $exception->formatAsFlashMessage();
        }

        return $this->defaultFormat($exception, $alertClass);
    }

    /**
     * Default formatting for exceptions without custom formatter.
     *
     * @param PtahException $exception
     * @param string $alertClass
     * @return string
     */
    protected function defaultFormat(PtahException $exception, string $alertClass): string
    {
        $html = sprintf('<div class="alert %s" role="alert">', htmlspecialchars($alertClass));

        // Title
        $title = $this->getErrorTitle($exception);
        $html .= '<strong>' . htmlspecialchars($title) . '</strong>';

        // Message
        if ($exception->getMessage()) {
            $html .= '<p class="mt-2 mb-0">' . htmlspecialchars($exception->getMessage()) . '</p>';
        }

        // Context
        $context = $exception->getContext();
        if (!empty($context)) {
            $html .= '<ul class="mt-2 mb-0 list-unstyled small">';
            foreach ($this->formatContext($context) as $key => $value) {
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
     * Format exception for Livewire flash message.
     *
     * Returns an array suitable for session()->flash().
     *
     * @param PtahException $exception
     * @return array<string, string>
     */
    public function formatForLivewire(PtahException $exception): array
    {
        return [
            'type' => 'error',
            'title' => $this->getErrorTitle($exception),
            'message' => $exception->getMessage(),
            'context' => $this->formatContext($exception->getContext()),
        ];
    }

    /**
     * Format exception as Tailwind alert component.
     *
     * @param PtahException $exception
     * @return string
     */
    public function formatTailwind(PtahException $exception): string
    {
        $html = '<div class="rounded-md bg-red-50 p-4">';
        $html .= '<div class="flex">';
        $html .= '<div class="flex-shrink-0">';
        $html .= '<svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">';
        $html .= '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="ml-3">';
        $html .= sprintf('<h3 class="text-sm font-medium text-red-800">%s</h3>', htmlspecialchars($this->getErrorTitle($exception)));

        if ($exception->getMessage()) {
            $html .= sprintf('<div class="mt-2 text-sm text-red-700">%s</div>', htmlspecialchars($exception->getMessage()));
        }

        $context = $exception->getContext();
        if (!empty($context)) {
            $html .= '<div class="mt-2 text-sm text-red-700"><ul class="list-disc pl-5 space-y-1">';
            foreach ($this->formatContext($context) as $key => $value) {
                $html .= sprintf(
                    '<li><strong>%s:</strong> %s</li>',
                    htmlspecialchars($key),
                    htmlspecialchars($value)
                );
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Get a human-readable error title.
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
     * Format multiple exceptions as a single alert.
     *
     * @param array<int, PtahException> $exceptions
     * @param string $alertClass
     * @return string
     */
    public function formatMultiple(array $exceptions, string $alertClass = 'alert-danger'): string
    {
        $html = sprintf('<div class="alert %s" role="alert">', htmlspecialchars($alertClass));
        $html .= '<strong>⚠️ Multiple Errors Detected</strong>';
        $html .= '<ul class="mt-2 mb-0">';

        foreach ($exceptions as $index => $exception) {
            $html .= sprintf(
                '<li><strong>Error #%d:</strong> %s</li>',
                $index + 1,
                htmlspecialchars($exception->getMessage())
            );
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
