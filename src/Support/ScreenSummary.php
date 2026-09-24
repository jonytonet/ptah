<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * One BaseCrud screen, in the few lines it takes to understand it.
 *
 * The config of a real screen is 3–10k tokens of JSON: every column carries
 * thirty keys, most of them defaults. What decides the next edit is much
 * less — which fields, of what type, shown where, searched or sorted how,
 * related to what, gated by which permission; which filters, actions, styles
 * and hooks exist. This keeps that and drops the rest, one line per column.
 *
 * Reads the stored config only; nothing is resolved or rendered (that is
 * `ptah:check`).
 */
final class ScreenSummary
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function build(string $model, string $route, array $config): array
    {
        $cols = array_values(array_filter((array) ($config['cols'] ?? []), 'is_array'));
        $data = array_values(array_filter($cols, fn ($c) => ($c['colsTipo'] ?? '') !== 'action'));
        $actions = array_values(array_filter($cols, fn ($c) => ($c['colsTipo'] ?? '') === 'action'));
        $permissions = (array) ($config['permissions'] ?? []);

        return [
            'model' => $model,
            'route' => $route,
            'class' => $config['crud'] ?? $model,
            'title' => $config['displayName'] ?? null,
            'permission' => $permissions['permissionIdentifier'] ?? null,
            'gates' => array_filter(array_intersect_key($permissions, array_flip(['create', 'edit', 'delete', 'export', 'restore'])), fn ($v) => is_string($v) && $v !== ''),
            'hidden_buttons' => array_keys(array_filter(
                array_intersect_key($permissions, array_flip(['showCreateButton', 'showEditButton', 'showDeleteButton', 'showTrashButton'])),
                fn ($v) => $v === false || $v === 'false' || $v === 0 || $v === '0' || $v === 'N'
            )),
            'columns' => array_map(self::column(...), $data),
            'filters' => array_map(fn (array $f) => trim(implode(' ', array_filter([
                (string) ($f['field'] ?? '?'),
                (string) ($f['colsFilterType'] ?? 'text'),
                ($f['defaultOperator'] ?? '=') !== '=' ? (string) $f['defaultOperator'] : null,
                ! empty($f['field_relation']) ? 'via '.$f['field_relation'] : (! empty($f['whereHas']) ? 'via '.$f['whereHas'] : null),
                ! empty($f['label']) ? '"'.$f['label'].'"' : null,
            ]))), array_values(array_filter((array) ($config['customFilters'] ?? []), 'is_array'))),
            'actions' => array_map(fn (array $a) => '"'.($a['colsNomeLogico'] ?? '?').'" '.($a['actionType'] ?? 'link').' '.($a['actionValue'] ?? '')
                .(! empty($a['actionPermission']) ? ' perm='.$a['actionPermission'] : ''), $actions),
            'styles' => array_map(fn (array $s) => trim(($s['field'] ?? '?').' '.($s['condition'] ?? $s['op'] ?? '?').' '.json_encode($s['value'] ?? null)), array_values(array_filter((array) ($config['contitionStyles'] ?? $config['conditionStyles'] ?? []), 'is_array'))),
            'joins' => array_map(fn (array $j) => trim(($j['type'] ?? 'left').' '.($j['table'] ?? '?').' on '.($j['first'] ?? $j['joinLeftColumn'] ?? '?').'='.($j['second'] ?? $j['joinRightColumn'] ?? '?')), array_values(array_filter((array) ($config['joins'] ?? []), 'is_array'))),
            'hooks' => array_filter(array_map(
                fn ($h) => is_array($h) ? ($h['handler'] ?? $h['code'] ?? null) : $h,
                (array) ($config['lifecycleHooks'] ?? [])
            ), fn ($h) => is_string($h) && $h !== ''),
            'settings' => array_filter([
                'perPage' => $config['uiPreferences']['perPage'] ?? null,
                'export' => isset($config['exportConfig']['enabled']) ? (bool) $config['exportConfig']['enabled'] : null,
                'import' => ! empty($config['importConfig']['enabled']) ? ($config['importConfig']['mode'] ?? 'create') : null,
                'groupBy' => $config['groupBy'] ?? null,
                'companyField' => $config['companyField'] ?? null,
                'rowLink' => $config['configLinkLinha'] ?? null,
                'masterDetail' => ! empty($config['masterDetail']) ? count((array) $config['masterDetail']) : null,
                'bulkActions' => ! empty($config['bulkActions']) ? count((array) $config['bulkActions']) : null,
                'notifications' => ! empty($config['notifications']['rules']) ? count((array) $config['notifications']['rules']) : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $col
     */
    private static function column(array $col): string
    {
        $flags = array_keys(array_filter([
            'hidden' => self::isOff($col['colsVisibleList'] ?? true),
            'form' => self::isOn($col['colsGravar'] ?? false) && ! self::isOff($col['colsEditableForm'] ?? true),
            'required' => self::isOn($col['colsRequired'] ?? false),
            'filter' => self::isOn($col['colsIsFilterable'] ?? false),
        ]));

        $extra = array_filter([
            ! empty($col['colsRenderer']) ? 'renderer='.$col['colsRenderer'] : null,
            ! empty($col['colsMask']) ? 'mask='.$col['colsMask'] : null,
            ! empty($col['colsRelacao']) ? 'rel='.$col['colsRelacao'].(! empty($col['colsRelacaoExibe']) ? '.'.$col['colsRelacaoExibe'] : '') : null,
            ! empty($col['colsRelacaoNested']) ? 'rel='.$col['colsRelacaoNested'] : null,
            ! empty($col['colsSDModel']) ? 'sd='.class_basename((string) $col['colsSDModel']).(! empty($col['colsSDLabel']) ? '.'.$col['colsSDLabel'] : '') : null,
            ! empty($col['colsSDService']) ? 'sd-service='.$col['colsSDService'] : null,
            ! empty($col['colsSelect']) && is_array($col['colsSelect']) ? 'options='.count($col['colsSelect']) : null,
            ! empty($col['colsOrderBy']) ? 'sort='.$col['colsOrderBy'] : null,
            ! empty($col['colsMetodoCustom']) ? 'computed='.$col['colsMetodoCustom'] : null,
            ! empty($col['colsValidations']) ? 'rules='.(is_array($col['colsValidations']) ? implode('|', $col['colsValidations']) : $col['colsValidations']) : null,
            ! empty($col['colsPermission']) ? 'perm='.$col['colsPermission'] : null,
        ]);

        return trim(sprintf(
            '%s  %s  "%s"%s%s',
            $col['colsNomeFisico'] ?? '?',
            ($col['colsTipo'] ?? '') !== '' ? $col['colsTipo'] : 'text',
            $col['colsNomeLogico'] ?? '',
            $flags !== [] ? '  ['.implode(',', $flags).']' : '',
            $extra !== [] ? '  '.implode(' ', $extra) : '',
        ));
    }

    /**
     * @param  array<string, mixed>  $s
     */
    public static function toText(array $s): string
    {
        $lines = [];
        $lines[] = $s['model'].($s['route'] !== '' ? "  [route {$s['route']}]" : '  [global]').($s['title'] ? '  "'.$s['title'].'"' : '');
        $lines[] = 'permission: '.($s['permission'] ?: '(none)')
            .($s['gates'] !== [] ? '  gates: '.implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($s['gates']), $s['gates'])) : '')
            .($s['hidden_buttons'] !== [] ? '  off: '.implode(',', $s['hidden_buttons']) : '');

        $lines[] = 'columns ('.count($s['columns']).'):';
        foreach ($s['columns'] as $c) {
            $lines[] = '  '.$c;
        }

        foreach (['filters', 'actions', 'styles', 'joins'] as $section) {
            if ($s[$section] !== []) {
                $lines[] = "{$section} (".count($s[$section]).'):';
                foreach ($s[$section] as $item) {
                    $lines[] = '  '.$item;
                }
            }
        }

        if ($s['hooks'] !== []) {
            $lines[] = 'hooks: '.implode('  ', array_map(fn ($k, $v) => "{$k}=".(strlen((string) $v) > 60 ? '(inline, '.strlen((string) $v).' chars)' : $v), array_keys($s['hooks']), $s['hooks']));
        }

        if ($s['settings'] !== []) {
            $lines[] = 'settings: '.implode('  ', array_map(fn ($k, $v) => $k.'='.(is_bool($v) ? ($v ? 'on' : 'off') : $v), array_keys($s['settings']), $s['settings']));
        }

        return implode("\n", $lines)."\n";
    }

    private static function isOn(mixed $v): bool
    {
        return $v === true || $v === 'S' || $v === 1 || $v === '1';
    }

    private static function isOff(mixed $v): bool
    {
        return $v === false || $v === 'N' || $v === 0 || $v === '0';
    }
}
