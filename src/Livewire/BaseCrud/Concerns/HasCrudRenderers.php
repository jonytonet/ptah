<?php

declare(strict_types=1);

namespace Ptah\Livewire\BaseCrud\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Cell rendering, row styling, helper formatters and custom method resolution.
 * Any user-visible text produced here uses trans() so it can be localised.
 */
trait HasCrudRenderers
{
    // ── Permission identifier ──────────────────────────────────────────────────

    /**
     * Returns the default permission identifier for the screen.
     * E.g. "products.index", "purchase.orders.index"
     */
    public function getDefaultPermissionIdentifier(): string
    {
        $model = str_replace(['/', '\\'], '.', strtolower($this->model));
        return $model . '.index';
    }

    // ── Cell formatting ────────────────────────────────────────────────────────

    /**
     * Formats a cell value according to the column configuration.
     * Applies colsRenderer DSL, colsRelacaoNested (dot notation),
     * colsRelacao/colsRelacaoExibe, colsHelper (legacy), colsMetodoCustom,
     * and select map in that order.
     */
    public function formatCell(array $col, mixed $row): string
    {
        $value = $this->getCellValue($col, $row);

        // colsMetodoCustom has maximum priority
        if (! empty($col['colsMetodoCustom'])) {
            $result = $this->resolveCustomMethod($col['colsMetodoCustom'], $row, $value);
            // colsMetodoRaw: true → returns raw HTML (explicit opt-in, trusts developer)
            return ($col['colsMetodoRaw'] ?? false) ? $result : e($result);
        }

        // Nested dot notation: "address.city.name" → data_get($row, 'address.city.name')
        if (! empty($col['colsRelacaoNested'])) {
            $value = $this->resolveNestedValue($row, $col['colsRelacaoNested']);
        } elseif (! empty($col['colsRelacao']) && ! empty($col['colsRelacaoExibe'])) {
            $rel   = $col['colsRelacao'];
            $exibe = $col['colsRelacaoExibe'];
            $value = $row->{$rel}?->{$exibe} ?? $value;
        }

        // Select: convert value to mapped label
        if (($col['colsTipo'] ?? '') === 'select' && ! empty($col['colsSelect'])) {
            $flip  = array_flip($col['colsSelect']);
            $value = $flip[(string) $value] ?? $value;
        }

        $rendered = $this->applyCellRenderer($col, $value, $row);

        // Optional icon and style wrappers configurable per column
        $cellIcon  = ! empty($col['colsCellIcon'])  ? '<span class="' . e($col['colsCellIcon']) . ' mr-1"></span>' : '';
        $cellStyle = ! empty($col['colsCellStyle']) ? ' style="' . e($col['colsCellStyle']) . '"' : '';
        $cellClass = ! empty($col['colsCellClass']) ? ' ' . e($col['colsCellClass']) : '';

        if ($cellIcon || $cellStyle || $cellClass) {
            return "<span{$cellStyle} class=\"inline-flex items-center{$cellClass}\">{$cellIcon}{$rendered}</span>";
        }

        return $rendered;
    }

    // ── Row style ──────────────────────────────────────────────────────────────

    /**
     * Returns the inline style for a row based on contitionStyles.
     */
    public function getRowStyle(mixed $row): string
    {
        $styles = $this->crudConfig['contitionStyles'] ?? [];

        foreach ($styles as $style) {
            $field     = $style['field']     ?? $style['colsNomeFisico'] ?? null;
            $condition = $style['condition'] ?? '==';
            $target    = $style['value']     ?? null;
            $css       = $style['style']     ?? '';

            if (! $field) {
                continue;
            }

            $rowValue = $row instanceof Model
                ? $row->getAttribute($field)
                : ($row[$field] ?? null);

            // Field does not exist on the model — silently skip
            if ($rowValue === null && $row instanceof Model && ! array_key_exists($field, $row->getAttributes())) {
                continue;
            }

            $match = match ($condition) {
                '==' => (string) $rowValue == (string) $target,
                '!=' => (string) $rowValue != (string) $target,
                '>'  => (float)  $rowValue >  (float)  $target,
                '<'  => (float)  $rowValue <  (float)  $target,
                '>=' => (float)  $rowValue >= (float)  $target,
                '<=' => (float)  $rowValue <= (float)  $target,
                default => false,
            };

            if ($match) {
                return $css;
            }
        }

        return '';
    }

    // ── Renderer DSL ───────────────────────────────────────────────────────────

    /**
     * Applies the configured renderer to a column value.
     * Routes to specific render* methods per colsRenderer.
     * Maintains backwards compatibility with the legacy colsHelper key.
     */
    protected function applyCellRenderer(array $col, mixed $value, mixed $row): string
    {
        $renderer = $col['colsRenderer'] ?? null;

        // Legacy compat: map colsHelper to renderer
        if (! $renderer && ! empty($col['colsHelper'])) {
            $renderer = match ($col['colsHelper']) {
                'dateFormat'     => 'date',
                'dateTimeFormat' => 'datetime',
                'currencyFormat' => 'money',
                'yesOrNot'       => 'boolean',
                'flagChannel'    => 'badge',
                default          => null,
            };

            // badge via compat — delegate to flagChannel helper
            if ($renderer === 'badge' && $col['colsHelper'] === 'flagChannel') {
                return $this->helperFlagChannel($value);
            }
        }

        if (! $renderer) {
            return e((string) ($value ?? ''));
        }

        return match ($renderer) {
            'badge'     => $this->renderBadge($col, $value),
            'pill'      => $this->renderPill($col, $value),
            'boolean'   => $this->renderBoolean($col, $value),
            'money'     => $this->renderMoney($col, $value),
            'date'      => $this->helperDateFormat($value),
            'datetime'  => $this->helperDateTimeFormat($value),
            'link'      => $this->renderLink($col, $value, $row),
            'image'     => $this->renderImage($col, $value),
            'truncate'  => $this->renderTruncate($col, $value),
            'number'    => $this->renderNumber($col, $value),
            'progress'  => $this->renderProgress($col, $value),
            'rating'    => $this->renderRating($col, $value),
            'color'     => $this->renderColor($value),
            'code'      => $this->renderCode($value),
            'filesize'  => $this->renderFilesize($value),
            'duration'  => $this->renderDuration($col, $value),
            'qrcode'    => $this->renderQrcode($col, $value),
            default     => e((string) ($value ?? '')),
        };
    }

    // ── Individual renderers ───────────────────────────────────────────────────

    /**
     * Renders a coloured badge based on a value map.
     * Config: colsRendererBadges => [{value, label, color, icon?}]
     */
    protected function renderBadge(array $col, mixed $value): string
    {
        $badges   = $col['colsRendererBadges'] ?? [];
        $valueStr = strtolower((string) ($value ?? ''));

        foreach ($badges as $badge) {
            if (strtolower((string) ($badge['value'] ?? '')) === $valueStr) {
                $label    = e($badge['label'] ?? $value);
                $colorVal = $badge['color'] ?? 'gray';
                $icon     = ! empty($badge['icon'])
                    ? '<span class="' . e($badge['icon']) . ' mr-1 text-[10px]"></span>'
                    : '';

                if (str_starts_with($colorVal, '#')) {
                    $hex = e($colorVal);
                    return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium\" style=\"background-color:{$hex}22;color:{$hex};border:1px solid {$hex}55\">{$icon}{$label}</span>";
                }

                $color = match (strtolower($colorVal)) {
                    'green', 'success'  => 'bg-green-100 text-green-800',
                    'yellow', 'warning' => 'bg-yellow-100 text-yellow-800',
                    'red', 'danger'     => 'bg-red-100 text-red-800',
                    'blue', 'info'      => 'bg-blue-100 text-blue-800',
                    'indigo', 'primary' => 'bg-indigo-100 text-indigo-800',
                    'purple'            => 'bg-purple-100 text-purple-800',
                    'pink'              => 'bg-pink-100 text-pink-800',
                    default             => 'bg-gray-100 text-gray-700',
                };
                return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {$color}\">{$icon}{$label}</span>";
            }
        }

        return '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700">' . e((string) ($value ?? '')) . '</span>';
    }

    /**
     * Renders a rounded pill badge based on a value map.
     * Config: colsRendererBadges => [{value, label, color, icon?}]
     */
    protected function renderPill(array $col, mixed $value): string
    {
        $badges   = $col['colsRendererBadges'] ?? [];
        $valueStr = strtolower((string) ($value ?? ''));

        foreach ($badges as $badge) {
            if (strtolower((string) ($badge['value'] ?? '')) === $valueStr) {
                $label    = e($badge['label'] ?? $value);
                $colorVal = $badge['color'] ?? 'gray';
                $icon     = ! empty($badge['icon'])
                    ? '<span class="' . e($badge['icon']) . ' mr-1 text-[10px]"></span>'
                    : '';

                if (str_starts_with($colorVal, '#')) {
                    $hex = e($colorVal);
                    return "<span class=\"inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold\" style=\"background-color:{$hex}22;color:{$hex};border:1px solid {$hex}55\">{$icon}{$label}</span>";
                }

                $color = match (strtolower($colorVal)) {
                    'green', 'success'  => 'bg-green-100 text-green-800',
                    'yellow', 'warning' => 'bg-yellow-100 text-yellow-800',
                    'red', 'danger'     => 'bg-red-100 text-red-800',
                    'blue', 'info'      => 'bg-blue-100 text-blue-800',
                    'indigo', 'primary' => 'bg-indigo-100 text-indigo-800',
                    'purple'            => 'bg-purple-100 text-purple-800',
                    default             => 'bg-gray-100 text-gray-700',
                };
                return "<span class=\"inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {$color}\">{$icon}{$label}</span>";
            }
        }

        return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">' . e((string) ($value ?? '')) . '</span>';
    }

    /**
     * Renders a boolean value as a Yes/No badge.
     * Config: colsRendererBoolTrue, colsRendererBoolFalse
     * Falls back to ptah::ui.bool_yes / bool_no translation keys.
     */
    protected function renderBoolean(array $col, mixed $value): string
    {
        $isTrue = in_array($value, [1, '1', 'S', 's', 'true', true, 'Y', 'y'], true);

        if ($isTrue) {
            $label = e($col['colsRendererBoolTrue'] ?? trans('ptah::ui.bool_yes'));
            return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800\">{$label}</span>";
        }

        $label = e($col['colsRendererBoolFalse'] ?? trans('ptah::ui.bool_no'));
        return "<span class=\"inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-500\">{$label}</span>";
    }

    /**
     * Renders a formatted monetary value.
     * Config: colsRendererCurrency (BRL/USD/EUR), colsRendererDecimals
     */
    protected function renderMoney(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';

        $currency = $col['colsRendererCurrency'] ?? 'BRL';
        $decimals = (int) ($col['colsRendererDecimals'] ?? 2);

        return match ($currency) {
            'USD'   => '$ '  . number_format((float) $value, $decimals, '.', ','),
            'EUR'   => '€ '  . number_format((float) $value, $decimals, ',', '.'),
            default => 'R$ ' . number_format((float) $value, $decimals, ',', '.'),
        };
    }

    /**
     * Renders a clickable hyperlink.
     * Config: colsRendererLinkTemplate (/path/%id%), colsRendererLinkLabel, colsRendererLinkNewTab
     * Supports %fieldName% placeholders for any field in the record.
     */
    protected function renderLink(array $col, mixed $value, mixed $row): string
    {
        $template = $col['colsRendererLinkTemplate'] ?? '#';
        $label    = $col['colsRendererLinkLabel']    ?? $value;
        $newTab   = ($col['colsRendererLinkNewTab']  ?? false)
            ? ' target="_blank" rel="noopener noreferrer"'
            : '';

        $url = str_replace('%value%', e((string) $value), $template);

        if ($row instanceof Model) {
            foreach ($row->getAttributes() as $k => $v) {
                $url = str_replace('%' . $k . '%', e((string) ($v ?? '')), $url);
            }
        }

        return "<a href=\"{$url}\"{$newTab} class=\"text-indigo-600 hover:text-indigo-800 hover:underline font-medium\">" . e((string) $label) . '</a>';
    }

    /**
     * Renders a thumbnail image.
     * Config: colsRendererImageWidth, colsRendererImageHeight
     */
    protected function renderImage(array $col, mixed $value): string
    {
        if (! $value) return '';

        $width  = (int) ($col['colsRendererImageWidth']  ?? 40);
        $height = (int) ($col['colsRendererImageHeight'] ?? $width);

        $v = (string) $value;
        // Resolve storage-relative paths to public URLs (requires `php artisan storage:link`)
        if (! str_starts_with($v, 'http') && ! str_starts_with($v, 'data:') && ! str_starts_with($v, '/')) {
            $v = asset('storage/' . ltrim($v, '/'));
        }

        return "<img src=\"" . e($v) . "\" width=\"{$width}\" height=\"{$height}\" class=\"rounded object-cover inline-block\" loading=\"lazy\" />";
    }

    /**
     * Renders truncated text with a hover tooltip for the full value.
     * Config: colsRendererMaxChars (default 50)
     */
    protected function renderTruncate(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';

        $max = (int) ($col['colsRendererMaxChars'] ?? 50);
        $str = (string) $value;

        if (mb_strlen($str) <= $max) {
            return e($str);
        }

        $truncated = mb_substr($str, 0, $max) . '…';
        return '<span title="' . e($str) . '" class="cursor-help">' . e($truncated) . '</span>';
    }

    /**
     * Renders a number formatted with locale-aware thousand/decimal separators.
     * Config: colsRendererDecimals (default 2), colsRendererLocale (default pt-BR)
     */
    protected function renderNumber(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $decimals = (int) ($col['colsRendererDecimals'] ?? 2);
        $locale   = $col['colsRendererLocale'] ?? 'pt-BR';

        return $locale === 'pt-BR'
            ? number_format((float) $value, $decimals, ',', '.')
            : number_format((float) $value, $decimals, '.', ',');
    }

    /**
     * Renders a visual progress bar (0–100 by default).
     * Config: colsRendererMax (default 100), colsRendererColor (green|blue|red|yellow|purple|indigo)
     */
    protected function renderProgress(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $max      = (float) ($col['colsRendererMax'] ?? 100);
        $pct      = $max > 0 ? min(100, round((float) $value * 100 / $max)) : 0;
        $colorKey = $col['colsRendererColor'] ?? 'blue';
        $bgBar    = match ($colorKey) {
            'green'  => 'bg-green-500',
            'red'    => 'bg-red-500',
            'yellow' => 'bg-yellow-500',
            'purple' => 'bg-purple-500',
            'indigo' => 'bg-indigo-500',
            default  => 'bg-blue-600',
        };

        return "<div class=\"flex items-center gap-2\">"
            . "<div class=\"flex-1 h-2 bg-gray-200 rounded-full overflow-hidden\">"
            . "<div class=\"{$bgBar} h-full rounded-full\" style=\"width:{$pct}%\"></div>"
            . "</div>"
            . "<span class=\"text-xs text-gray-600 tabular-nums w-9 text-right\">{$pct}%</span>"
            . "</div>";
    }

    /**
     * Renders a star rating (1–N stars).
     * Config: colsRendererMax (default 5)
     */
    protected function renderRating(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $max   = (int) ($col['colsRendererMax'] ?? 5);
        $score = (float) $value;
        $html  = '<span class="inline-flex items-center gap-0.5" aria-label="' . e($score) . ' of ' . $max . '">';
        for ($i = 1; $i <= $max; $i++) {
            if ($score >= $i) {
                $html .= '<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            } elseif ($score >= $i - 0.5) {
                $html .= '<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0L6.6 15.207c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" clip-path="inset(0 50% 0 0)"/></svg>';
            } else {
                $html .= '<svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>';
            }
        }
        return $html . '</span>';
    }

    /**
     * Renders a hex colour swatch with the colour code alongside.
     * E.g. value "#FF5733" → ■ #FF5733
     */
    protected function renderColor(mixed $value): string
    {
        if (! $value) return '';
        $hex = e((string) $value);
        return "<span class=\"inline-flex items-center gap-1.5\">"
            . "<span class=\"inline-block rounded border border-gray-300\" style=\"width:16px;height:16px;background:{$hex};flex-shrink:0\"></span>"
            . "<code class=\"text-xs font-mono text-gray-700\">{$hex}</code>"
            . "</span>";
    }

    /**
     * Renders text as a monospace code snippet.
     */
    protected function renderCode(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        return '<code class="text-xs font-mono bg-gray-100 text-gray-800 px-1.5 py-0.5 rounded border border-gray-200">'
            . e((string) $value) . '</code>';
    }

    /**
     * Renders a human-readable file size (field value in bytes).
     * E.g. 1536000 → "1.5 MB"
     */
    protected function renderFilesize(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $bytes = (float) $value;
        if ($bytes < 1_024)           return number_format($bytes, 0, ',', '.') . ' B';
        if ($bytes < 1_048_576)       return number_format($bytes / 1_024, 1, ',', '.') . ' KB';
        if ($bytes < 1_073_741_824)   return number_format($bytes / 1_048_576, 1, ',', '.') . ' MB';
        return number_format($bytes / 1_073_741_824, 2, ',', '.') . ' GB';
    }

    /**
     * Renders a human-readable duration.
     * Config: colsRendererDurationUnit (minutes|seconds, default minutes)
     * E.g. 95 minutes → "1h 35min"
     */
    protected function renderDuration(array $col, mixed $value): string
    {
        if ($value === null || $value === '') return '';
        $unit    = $col['colsRendererDurationUnit'] ?? 'minutes';
        $seconds = $unit === 'seconds' ? (int) $value : (int) $value * 60;
        $h       = intdiv($seconds, 3600);
        $m       = intdiv($seconds % 3600, 60);
        $s       = $seconds % 60;
        if ($h > 0 && $unit !== 'seconds') return "{$h}h {$m}min";
        if ($h > 0)  return "{$h}h {$m}min {$s}s";
        if ($m > 0)  return "{$m}min" . ($s > 0 ? " {$s}s" : '');
        return "{$s}s";
    }

    /**
     * Renders a QR code via qrcode.js (CDN) using Alpine.js for client-side generation.
     * Config: colsRendererQrSize (default 64, in px)
     */
    protected function renderQrcode(array $col, mixed $value): string
    {
        if (! $value) return '';
        $size    = (int) ($col['colsRendererQrSize'] ?? 64);
        $escaped = e((string) $value);
        return "<span x-data x-init=\"\$nextTick(() => { if(window.QRCode) new QRCode(\$el.querySelector('div'), {text:'{$escaped}',width:{$size},height:{$size},colorDark:'#1a1a1a',colorLight:'#fff'}); })\">"
            . "<div title=\"{$escaped}\"></div>"
            . "</span>";
    }

    // ── Helpers and formatters ─────────────────────────────────────────────────

    /**
     * Resolves a nested value via dot notation using Laravel's data_get().
     * Supports: "address.city.name", "items.0.price", objects, arrays, null-safe.
     */
    protected function resolveNestedValue(mixed $row, string $path): mixed
    {
        return data_get($row, $path);
    }

    /**
     * Dispatches to the appropriate helper formatter by name.
     */
    protected function applyHelper(string $helper, mixed $value): mixed
    {
        return match ($helper) {
            'dateFormat'     => $this->helperDateFormat($value),
            'dateTimeFormat' => $this->helperDateTimeFormat($value),
            'currencyFormat' => $this->helperCurrencyFormat($value),
            'yesOrNot'       => $this->helperYesOrNot($value),
            'flagChannel'    => $this->helperFlagChannel($value),
            default          => $value,
        };
    }

    /**
     * Formats a value as a locale date (d/m/Y).
     */
    protected function helperDateFormat(mixed $value): string
    {
        if (! $value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Formats a value as a locale date-time (d/m/Y H:i).
     */
    protected function helperDateTimeFormat(mixed $value): string
    {
        if (! $value) return '';
        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * Formats a value as BRL currency (R$ 1.234,56).
     */
    protected function helperCurrencyFormat(mixed $value): string
    {
        if ($value === null || $value === '') return '';
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    /**
     * Returns the translated "Yes" or "No" label for a truthy/falsy value.
     * Truthy set: 1, '1', 'S', 'true', true.
     */
    protected function helperYesOrNot(mixed $value): string
    {
        return in_array($value, [1, '1', 'S', 'true', true], true)
            ? trans('ptah::ui.bool_yes')
            : trans('ptah::ui.bool_no');
    }

    /**
     * Renders a traffic-light channel badge (G/Y/R → Green/Yellow/Red).
     * Colours are localised via trans() keys.
     */
    protected function helperFlagChannel(mixed $value): string
    {
        return match (strtoupper((string) $value)) {
            'G' => '<span class="badge" style="background:#28a745">' . trans('ptah::ui.flag_green')  . '</span>',
            'Y' => '<span class="badge" style="background:#ffc107;color:#000">' . trans('ptah::ui.flag_yellow') . '</span>',
            'R' => '<span class="badge" style="background:#dc3545">' . trans('ptah::ui.flag_red')    . '</span>',
            default => (string) $value,
        };
    }

    // ── Custom method resolver ─────────────────────────────────────────────────

    /**
     * Resolves and calls the pattern "Namespace\Class\Method(%field1%, %field2%, 'literal')"
     * defined in colsMetodoCustom.
     *
     * Syntax:
     *   "Path\Service\method(%field%)"                          → 1 argument
     *   "Path\Service\method(%field1%, %field2%, 'literal')"    → N arguments
     *
     * The prefix "App\Services\" is added automatically.
     * Always returns (string) — use colsMetodoRaw: true for raw HTML output.
     */
    protected function resolveCustomMethod(string $pattern, mixed $row, mixed $value): string
    {
        if (! preg_match('/^(.+)\\\\(\w+)\((.*)\)$/', $pattern, $m)) {
            return (string) $value;
        }

        $classPath = $m[1];
        $method    = $m[2];
        $paramStr  = trim($m[3]);

        $args = $paramStr !== ''
            ? array_map(function (string $token) use ($row): mixed {
                $token = trim($token);

                // %fieldName% → field value from the record
                if (preg_match('/^%([\w\.]+)%$/', $token, $pm)) {
                    $f = $pm[1];
                    return $row instanceof Model
                        ? ($row->getAttribute($f) ?? data_get($row, $f) ?? '')
                        : ($row[$f] ?? '');
                }

                // Single-quoted string literal: 'value' → value
                if (preg_match("/^'(.*)'$/s", $token, $pm)) {
                    return $pm[1];
                }

                // Double-quoted string literal: "value" → value
                if (preg_match('/^"(.*)"$/s', $token, $pm)) {
                    return $pm[1];
                }

                // Numeric
                if (is_numeric($token)) {
                    return $token + 0;
                }

                return $token;
            }, str_getcsv($paramStr))
            : [];

        $class = 'App\\Services\\' . str_replace('/', '\\', $classPath);

        try {
            if (class_exists($class) && method_exists($class, $method)) {
                $result = app($class)->{$method}(...$args);
                return (string) $result;
            }
        } catch (\Throwable) {
            // Fail silently and return the original value
        }

        return e((string) $value);
    }

    // ── Utility ────────────────────────────────────────────────────────────────

    /**
     * Accepts both boolean (true/false) and legacy string ('S'/'N').
     * Returns true for: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
