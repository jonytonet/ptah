<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Normalises a conditional row-style rule into the single canonical shape
 * `{field, condition, value, style}` — the shape HasCrudRenderers::getRowStyle()
 * consumes and reads from the `contitionStyles` key (the typo stays: it is the
 * runtime's actual persisted contract, see getRowStyle()).
 *
 * `--style=` (StyleParser), the interactive wizard (StyleWizard) and legacy
 * schema-validated rows (colsNomeFisico/colsOperator/colsValue/colsCss) all
 * funnel through here, so there is exactly one place that decides whether a
 * rule is usable at render time.
 */
final class StyleRule
{
    /**
     * The six comparison operators HasCrudRenderers::getRowStyle()'s match()
     * expression actually evaluates. Deliberately NOT the SQL-filter operator
     * list (CrudConfigEnums::OPERATORS) — '=' and 'LIKE' have no arm there and
     * would silently never match at render time.
     */
    public const CONDITIONS = ['==', '!=', '>', '<', '>=', '<='];

    /**
     * Aliased condition tokens mapped onto the canonical symbol above.
     * Anything else (including 'LIKE') is rejected, not merely left unmapped.
     */
    private const CONDITION_ALIASES = [
        'eq' => '==',
        'ne' => '!=',
        'lt' => '<',
        'gt' => '>',
        'lte' => '<=',
        'gte' => '>=',
        '=' => '==',
    ];

    /**
     * Normalises a single style rule.
     *
     * Accepts the canonical keys (field/condition/value/style) and the legacy
     * aliases (colsNomeFisico|styleField for field, colsOperator for condition,
     * colsValue for value, colsCss for style), in that precedence order.
     *
     * Returns null when the rule cannot be used at render time: empty field,
     * empty style, or a condition that does not map onto CONDITIONS.
     *
     * @param  array<string, mixed>  $rule
     * @return array{field: string, condition: string, value: mixed, style: string}|null
     */
    public static function normalize(array $rule): ?array
    {
        $field = self::firstNonEmptyString($rule, ['field', 'colsNomeFisico', 'styleField']);
        $style = self::firstNonEmptyString($rule, ['style', 'colsCss']);

        if ($field === null || $style === null) {
            return null;
        }

        $condition = self::firstPresent($rule, ['condition', 'colsOperator']) ?? '==';
        $condition = self::CONDITION_ALIASES[$condition] ?? $condition;

        if (! in_array($condition, self::CONDITIONS, true)) {
            return null;
        }

        return [
            'field' => $field,
            'condition' => $condition,
            'value' => self::extractValue($rule),
            'style' => $style,
        ];
    }

    /**
     * Reads "value" (or its legacy alias "colsValue") with array_key_exists —
     * NOT isset()/?? — because 0 and '' are legitimate comparison values that
     * isset() would wrongly treat as absent.
     */
    private static function extractValue(array $rule): mixed
    {
        if (array_key_exists('value', $rule)) {
            return $rule['value'];
        }

        if (array_key_exists('colsValue', $rule)) {
            return $rule['colsValue'];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function firstNonEmptyString(array $rule, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($rule[$key] ?? null)) {
                return (string) $rule[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function firstPresent(array $rule, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $rule)) {
                return $rule[$key];
            }
        }

        return null;
    }
}
