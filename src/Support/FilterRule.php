<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Normalises a custom-filter definition into the single canonical shape the
 * runtime consumes — the one `FilterService::processCustomFilters()` reads out
 * of the `customFilters` key of a screen's config.
 *
 * This exists for the same reason {@see StyleRule} does, and the defect it
 * closes was the same shape: three dialects and a runtime nobody had checked
 * against. Measured on 1.27.0, before this class:
 *
 *   - `--filter=` (FilterParser) emitted `field` / `colsFilterType` /
 *     `defaultOperator` / `field_relation` — which the runtime does accept;
 *   - the interactive wizard (FilterWizard) emitted `colsFilterField` /
 *     `colsFilterLabel` / `colsFilterOperator` — which the runtime reads as
 *     nothing at all, so every filter added through the wizard was inert;
 *   - `ConfigSchemaValidator` required `colsNomeFisico`, a key neither of them
 *     produced, so `ptah:config --filter=` failed validation on every call;
 *   - and `ConfigCommand` wrote all of it to `config['filters']`, a section no
 *     runtime code reads (the runtime reads `customFilters`), so even a filter
 *     that had passed validation would never have been applied.
 *
 * The canonical vocabulary is the runtime's, not the prettiest one: whatever
 * `processCustomFilters()` reads is what a filter has to look like, and every
 * writer funnels through here so there is exactly one place that decides
 * whether a filter is usable at query time.
 */
final class FilterRule
{
    /**
     * The section of a screen's config that the runtime actually reads.
     * `filters` (without the prefix) is a legacy orphan: written by older
     * `ptah:config` runs, validated by the schema validator, read by nothing.
     * `ptah:config:doctor` migrates it.
     */
    public const SECTION = 'customFilters';

    /**
     * Legacy section key kept only so the doctor can find and migrate rows
     * that still carry it.
     */
    public const LEGACY_SECTION = 'filters';

    /**
     * Field-name aliases, in the order they are tried. `field` is canonical;
     * the rest are the dialects found in the wild.
     */
    private const FIELD_KEYS = ['field', 'colsFilterField', 'colsNomeFisico'];

    private const LABEL_KEYS = ['label', 'colsFilterLabel', 'colsNomeLogico'];

    private const TYPE_KEYS = ['type', 'colsFilterType'];

    private const OPERATOR_KEYS = ['operator', 'defaultOperator', 'colsFilterOperator', 'colsOperator'];

    private const RELATION_KEYS = ['colRelation', 'field_relation'];

    /**
     * Normalises one filter definition.
     *
     * Returns null when the filter cannot be applied at query time — which,
     * per `processCustomFilters()`, means only one thing: no field to filter
     * on. Everything else has a working default there, so rejecting more than
     * this would refuse configs the runtime handles fine.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>|null
     */
    public static function normalize(array $filter): ?array
    {
        $field = self::firstNonEmptyString($filter, self::FIELD_KEYS);

        if ($field === null) {
            return null;
        }

        $normalized = [
            'field' => $field,
            'label' => self::firstNonEmptyString($filter, self::LABEL_KEYS)
                ?? ucfirst(str_replace('_', ' ', $field)),
            // Both spellings are emitted: `type`/`operator` are what
            // processCustomFilters() prefers, and the `colsFilterType`/
            // `defaultOperator` aliases are kept because the visual editor and
            // older saved configs read them back when re-opening a screen.
            'type' => self::firstNonEmptyString($filter, self::TYPE_KEYS) ?? 'text',
            'colsFilterType' => self::firstNonEmptyString($filter, self::TYPE_KEYS) ?? 'text',
            'operator' => self::firstNonEmptyString($filter, self::OPERATOR_KEYS) ?? '=',
            'defaultOperator' => self::firstNonEmptyString($filter, self::OPERATOR_KEYS) ?? '=',
            'whereHas' => self::firstNonEmptyString($filter, ['whereHas']) ?? '',
            'colRelation' => self::firstNonEmptyString($filter, self::RELATION_KEYS) ?? '',
            'field_relation' => self::firstNonEmptyString($filter, self::RELATION_KEYS) ?? '',
            'aggregate' => self::firstNonEmptyString($filter, ['aggregate']) ?? '',
            'logic' => self::firstNonEmptyString($filter, ['logic']) ?? 'AND',
        ];

        // Passthrough for keys this normaliser does not model — the select
        // options list and the searchdropdown source columns, which the filter
        // panel renders straight from the config. Dropping them would silently
        // empty a select the user had configured.
        //
        // `colsSelect` is the key the panel actually reads (`$cf['colsSelect']`
        // in _filter-panel.blade.php); `options` is kept alongside it only so a
        // config saved before that was corrected still round-trips through here
        // untouched, ready for `ptah:config:doctor --fix` to migrate.
        foreach (['colsSelect', 'options', 'colsFilterSdTable', 'colsFilterSdValueColumn', 'colsFilterSdSelectColumn'] as $passthrough) {
            if (isset($filter[$passthrough]) && $filter[$passthrough] !== '') {
                $normalized[$passthrough] = $filter[$passthrough];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $filter
     */
    private static function firstNonEmptyString(array $filter, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! empty($filter[$key]) && is_scalar($filter[$key])) {
                return (string) $filter[$key];
            }
        }

        return null;
    }
}
