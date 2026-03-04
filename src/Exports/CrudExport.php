<?php

namespace Ptah\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CrudExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Builder $query;
    protected array $columns = [];

    public function __construct(Builder $query, array $columns = [])
    {
        $this->query   = $query;
        $this->columns = $columns;
    }

    /**
     * Query para buscar os dados
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * Cabeçalhos das colunas
     */
    public function headings(): array
    {
        // Se não foram passadas colunas específicas, pegar todas do primeiro registro
        if (empty($this->columns)) {
            $first = $this->query->first();
            
            if (!$first) {
                return [];
            }

            return array_map(function ($column) {
                return ucwords(str_replace('_', ' ', $column));
            }, array_keys($first->toArray()));
        }

        // Usar labels das colunas visíveis
        return array_map(fn($col) => $col['label'] ?? '', $this->columns);
    }

    /**
     * Mapear cada linha
     */
    public function map($row): array
    {
        $mapped = [];
        
        // Se não foram passadas colunas específicas, usar todas
        if (empty($this->columns)) {
            $fields = array_keys($row->toArray());
            
            foreach ($fields as $field) {
                $mapped[] = $this->formatValue($row->{$field});
            }
            
            return $mapped;
        }

        // Usar apenas as colunas visíveis
        foreach ($this->columns as $column) {
            $field = $column['field'] ?? '';
            $value = data_get($row, $field);
            $mapped[] = $this->formatValue($value, $column['type'] ?? '');
        }

        return $mapped;
    }

    /**
     * Formatar valor para exportação
     */
    protected function formatValue($value, string $type = '')
    {
        // Formatar datas
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }
        
        // Formatar booleanos
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        
        // Formatar valores de select/enum se necessário
        if ($type === 'select' && is_numeric($value)) {
            // Aqui poderia buscar o label do select, mas por ora retorna o valor
            return $value;
        }
        
        // Outros valores
        return $value ?? '';
    }

    /**
     * Estilos para o cabeçalho
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}
