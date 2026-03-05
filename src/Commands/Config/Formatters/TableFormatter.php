<?php

namespace Ptah\Commands\Config\Formatters;

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

        $this->formatGeneral($config);
        $this->formatColumns($config['formEditFields'] ?? []);
        $this->formatActions($config['crudActions'] ?? []);
        $this->formatFilters($config['formEditFields'] ?? []);
        $this->formatStyles($config['customRowStyles'] ?? []);
        $this->formatJoins($config['leftJoins'] ?? []);
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

        $settings = [
            'Display Name' => $config['displayName'] ?? 'N/A',
            'Cache Enabled' => $config['cacheEnabled'] ? 'Yes' : 'No',
            'Cache TTL' => $config['cacheTtl'] ? $config['cacheTtl'] . 's' : 'N/A',
            'Export Max Rows' => $config['exportMaxRows'] ?? 'N/A',
            'Theme' => $config['theme'] ?? 'N/A',
            'Orientation' => $config['pdfOrientation'] ?? 'N/A',
            'Paper Size' => $config['pdfPaperSize'] ?? 'N/A',
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
        $displayColumns = array_filter($fields, fn($f) => ($f['colsVisivel'] ?? true));

        if (empty($displayColumns)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== COLUMNS (" . count($displayColumns) . ") ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Type', 'Label', 'Required', 'Sortable', 'Filterable', 'Renderer', 'Mask']);

        foreach ($displayColumns as $field) {
            $table->addRow([
                $field['colsNomeFisico'] ?? '',
                $field['colsTipo'] ?? 'text',
                $field['colsNomeLogico'] ?? '',
                ($field['colsRequerido'] ?? false) ? 'Yes' : 'No',
                ($field['colsSortable'] ?? false) ? 'Yes' : 'No',
                ($field['colsFilterable'] ?? false) ? 'Yes' : 'No',
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

        $this->output->writeln("\n<fg=green;options=bold>=== CUSTOM ACTIONS (" . count($actions) . ") ===</>");

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
    protected function formatFilters(array $fields): void
    {
        $filterableFields = array_filter($fields, fn($f) => ($f['colsFilterable'] ?? false));

        if (empty($filterableFields)) {
            return;
        }

        $this->output->writeln("\n<fg=green;options=bold>=== FILTERS (" . count($filterableFields) . ") ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Type', 'Label', 'Operator', 'Relation']);

        foreach ($filterableFields as $field) {
            $table->addRow([
                $field['colsNomeFisico'] ?? '',
                $field['colsFilterType'] ?? 'text',
                $field['colsNomeLogico'] ?? '',
                $field['defaultOperator'] ?? '=',
                $field['whereHas'] ?? '-',
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

        $this->output->writeln("\n<fg=green;options=bold>=== CONDITIONAL STYLES (" . count($styles) . ") ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Field', 'Condition', 'Value', 'Style']);

        foreach ($styles as $style) {
            $stylePreview = substr($style['style'] ?? '', 0, 50);
            if (strlen($style['style'] ?? '') > 50) {
                $stylePreview .= '...';
            }

            $table->addRow([
                $style['field'] ?? '',
                $style['condition'] ?? '',
                $style['value'] ?? '',
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

        $this->output->writeln("\n<fg=green;options=bold>=== JOINS (" . count($joins) . ") ===</>");

        $table = new Table($this->output);
        $table->setHeaders(['Type', 'Table', 'ON Clause', 'Select', 'Distinct']);

        foreach ($joins as $join) {
            $onClause = ($join['first'] ?? '') . ' = ' . ($join['second'] ?? '');

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

        $permissions = [
            'Create' => [
                'gate' => $config['gateCreate'] ?? '-',
                'visible' => ($config['btnNewVisible'] ?? true) ? 'Yes' : 'No',
            ],
            'Edit' => [
                'gate' => $config['gateEdit'] ?? '-',
                'visible' => ($config['btnEditVisible'] ?? true) ? 'Yes' : 'No',
            ],
            'Delete' => [
                'gate' => $config['gateDelete'] ?? '-',
                'visible' => ($config['btnDeleteVisible'] ?? true) ? 'Yes' : 'No',
            ],
            'View' => [
                'gate' => $config['gateView'] ?? '-',
                'visible' => ($config['btnViewVisible'] ?? true) ? 'Yes' : 'No',
            ],
        ];

        foreach ($permissions as $action => $perm) {
            $table->addRow([$action, $perm['gate'], $perm['visible']]);
        }

        $table->render();

        $this->output->writeln('');
    }
}
