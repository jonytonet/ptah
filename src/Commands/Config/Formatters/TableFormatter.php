<?php

namespace Ptah\Commands\Config\Formatters;

use Ptah\Support\StyleRule;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class TableFormatter
{
    protected OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Format full configuration as tables
     */
    public function format(array $config, ?string $model = null): void
    {
        if ($model) {
            $this->output->writeln("\n<fg=cyan;options=bold>Configuration for: {$model}</>");
        }

        // O formato ATUAL (cols, permissions.*, cacheStrategy, contitionStyles,
        // joins), com o antigo de alternativa (formEditFields, btn*Visible,
        // cacheEnabled, customRowStyles, leftJoins). So o antigo era lido, e o
        // --list de qualquer tela mostrava zero colunas e botoes "Yes" (1.46.3).
        $cols = (array) ($config['cols'] ?? $config['formEditFields'] ?? []);
        $actions = array_values(array_filter($cols, fn ($c) => is_array($c) && ($c['colsTipo'] ?? '') === 'action'));
        $fields = array_values(array_filter($cols, fn ($c) => is_array($c) && ($c['colsTipo'] ?? '') !== 'action'));

        $this->formatGeneral($config);
        $this->formatColumns($fields);
        $this->formatActions($actions !== [] ? $actions : (array) ($config['crudActions'] ?? []));
        $this->formatFilters($fields, (array) ($config['customFilters'] ?? []));
        $this->formatStyles((array) ($config['contitionStyles'] ?? $config['conditionStyles'] ?? $config['customRowStyles'] ?? []));
        $this->formatJoins((array) ($config['joins'] ?? $config['leftJoins'] ?? []));
        $this->formatPermissions($config);
    }

    /**
     * Format general settings
     */
    protected function formatGeneral(array $config): void
    {
        $this->output->writeln("\n<fg=green;options=bold>=== GENERAL SETTINGS ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Setting', 'Value']);

        $cache = (array) ($config['cacheStrategy'] ?? []);
        $export = (array) ($config['exportConfig'] ?? []);
        $cacheOn = $cache['enabled'] ?? $config['cacheEnabled'] ?? false;
        $ttl = $cache['ttl'] ?? $config['cacheTtl'] ?? null;

        $settings = [
            'Display Name' => $config['displayName'] ?? 'N/A',
            'Permission Identifier' => $config['permissions']['permissionIdentifier'] ?? $config['permissionIdentifier'] ?? '-',
            'Cache Enabled' => $this->yes($cacheOn) ? 'Yes' : 'No',
            'Cache TTL' => $ttl ? $ttl.'s' : 'N/A',
            'Export Max Rows' => $export['maxRows'] ?? $config['exportMaxRows'] ?? 'N/A',
            'Orientation' => $export['orientation'] ?? $config['pdfOrientation'] ?? 'N/A',
            'Paper Size' => $export['paperSize'] ?? $config['pdfPaperSize'] ?? 'N/A',
        ];

        foreach ($settings as $name => $value) {
            $table->addRow([$name, $value]);
        }

        $table->render();
    }

    /**
     * Format columns
     */
    protected function formatColumns(array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== COLUMNS (".count($fields).') ===</>');

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Type', 'Label', 'In list', 'Form', 'Required', 'Filterable', 'Renderer', 'Mask']);

        foreach ($fields as $field) {
            $table->addRow([
                $field['colsNomeFisico'] ?? '',
                $field['colsTipo'] ?? 'text',
                $field['colsNomeLogico'] ?? '',
                $this->yes($field['colsVisibleList'] ?? $field['colsVisivel'] ?? true) ? 'Yes' : 'No',
                $this->yes($field['colsGravar'] ?? false) ? 'Yes' : 'No',
                $this->yes($field['colsRequired'] ?? $field['colsRequerido'] ?? false) ? 'Yes' : 'No',
                $this->yes($field['colsIsFilterable'] ?? $field['colsFilterable'] ?? false) ? 'Yes' : 'No',
                $field['colsRenderer'] ?? '-',
                $field['colsMask'] ?? '-',
            ]);
        }

        $table->render();
    }

    /**
     * Format custom actions
     */
    protected function formatActions(array $actions): void
    {
        if (empty($actions)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== CUSTOM ACTIONS (".count($actions).') ===</>');

        $table = new Table($this->output);
        $table->setHeaders(['Name', 'Type', 'Value', 'Icon', 'Color', 'Permission']);

        foreach ($actions as $action) {
            $table->addRow([
                $action['colsNomeLogico'] ?? '',
                $action['actionType'] ?? '',
                $action['actionValue'] ?? '',
                $action['actionIcon'] ?? '-',
                $action['actionColor'] ?? '-',
                $action['actionPermission'] ?? '-',
            ]);
        }

        $table->render();
    }

    /**
     * Format filters
     */
    protected function formatFilters(array $fields, array $customFilters = []): void
    {
        $filterableFields = array_filter($fields, fn ($f) => $this->yes($f['colsIsFilterable'] ?? $f['colsFilterable'] ?? false));

        if (empty($filterableFields) && empty($customFilters)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== FILTERS (".(count($filterableFields) + count($customFilters)).') ===</>');

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Type', 'Label', 'Kind', 'Relation']);

        foreach ($filterableFields as $field) {
            $table->addRow([
                $field['colsNomeFisico'] ?? '',
                $field['colsFilterType'] ?? $field['colsTipo'] ?? 'text',
                $field['colsNomeLogico'] ?? '',
                'column',
                $field['colsRelacao'] ?? $field['whereHas'] ?? '-',
            ]);
        }

        foreach ($customFilters as $cf) {
            if (! is_array($cf)) {
                continue;
            }
            $table->addRow([
                $cf['field'] ?? '',
                $cf['colsFilterType'] ?? $cf['type'] ?? 'text',
                $cf['label'] ?? '',
                'custom',
                $cf['whereHas'] ?? '-',
            ]);
        }

        $table->render();
    }

    /**
     * Format styles
     */
    protected function formatStyles(array $styles): void
    {
        if (empty($styles)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== CONDITIONAL STYLES (".count($styles).') ===</>');

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Condition', 'Value', 'Style']);

        foreach ($styles as $raw) {
            // StyleRule le os dois formatos (field/condition/style e os aliases
            // colsNomeFisico/colsOperator/colsCss).
            $style = is_array($raw) ? (StyleRule::normalize($raw) ?? $raw) : [];
            $stylePreview = substr((string) ($style['style'] ?? ''), 0, 50);
            if (strlen((string) ($style['style'] ?? '')) > 50) {
                $stylePreview .= '...';
            }

            $table->addRow([
                $style['field'] ?? '',
                $style['condition'] ?? '',
                is_scalar($style['value'] ?? '') ? (string) ($style['value'] ?? '') : json_encode($style['value']),
                $stylePreview,
            ]);
        }

        $table->render();
    }

    /**
     * Format joins
     */
    protected function formatJoins(array $joins): void
    {
        if (empty($joins)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== JOINS (".count($joins).') ===</>');

        $table = new Table($this->output);
        $table->setHeaders(['Type', 'Table', 'ON Clause', 'Select', 'Distinct']);

        foreach ($joins as $join) {
            $onClause = ($join['first'] ?? '').' = '.($join['second'] ?? '');

            $table->addRow([
                strtoupper($join['type'] ?? 'left'),
                $join['table'] ?? '',
                $onClause,
                $join['select'] ?? '-',
                ($join['distinct'] ?? false) ? 'Yes' : 'No',
            ]);
        }

        $table->render();
    }

    /**
     * Format permissions
     */
    protected function formatPermissions(array $config): void
    {
        $this->output->writeln("\n<fg=green;options=bold>=== PERMISSIONS ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Action', 'Gate', 'Button Visible']);

        $p = (array) ($config['permissions'] ?? []);
        $visible = fn (string $now, string $legacy): string => $this->yes($p[$now] ?? $config[$legacy] ?? true) ? 'Yes' : 'No';
        $permissions = [
            'Create' => ['gate' => $p['create'] ?? $config['gateCreate'] ?? '-', 'visible' => $visible('showCreateButton', 'btnNewVisible')],
            'Edit' => ['gate' => $p['edit'] ?? $config['gateEdit'] ?? '-', 'visible' => $visible('showEditButton', 'btnEditVisible')],
            'Delete' => ['gate' => $p['delete'] ?? $config['gateDelete'] ?? '-', 'visible' => $visible('showDeleteButton', 'btnDeleteVisible')],
            'Export' => ['gate' => $p['export'] ?? '-', 'visible' => '-'],
            'Restore' => ['gate' => $p['restore'] ?? '-', 'visible' => $visible('showTrashButton', 'btnTrashVisible')],
        ];

        foreach ($permissions as $action => $perm) {
            $table->addRow([$action, $perm['gate'] ?: '-', $perm['visible']]);
        }

        $table->render();

        $this->output->writeln('');
    }

    /** The config's booleans come as true/'S'/1/'1'/'true'. */
    private function yes(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'S', 's', 'true', 'yes', 'Y'], true);
    }
}
