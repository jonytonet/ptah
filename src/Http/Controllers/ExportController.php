<?php

namespace Ptah\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Ptah\Exports\CrudExport;

class ExportController
{
    /**
     * Exporta dados filtrados (Excel ou PDF)
     */
    public function export(Request $request)
    {
        $modelClass = $request->input('model');
        $format     = $request->input('format', 'excel');
        $filters    = json_decode($request->input('filters', '{}'), true);
        $columns    = json_decode($request->input('columns', '[]'), true);

        // Validar que o model existe
        if (!class_exists($modelClass)) {
            abort(404, 'Model não encontrado');
        }

        $model = App::make($modelClass);
        $query = $model::query();

        // Aplicar filtros
        $this->applyFilters($query, $filters);

        // Nome do arquivo
        $modelName = class_basename($modelClass);
        $fileName  = Str::slug($modelName) . '-' . now()->format('Y-m-d-His');

        if ($format === 'pdf') {
            return $this->exportPdf($query, $fileName, $modelName, $columns);
        }

        return $this->exportExcel($query, $fileName, $columns);
    }

    /**
     * Exporta itens selecionados (bulk export)
     */
    public function bulkExport(Request $request)
    {
        $modelClass = $request->input('model');
        $format     = $request->input('format', 'excel');
        $ids        = json_decode($request->input('ids', '[]'), true);
        $columns    = json_decode($request->input('columns', '[]'), true);

        if (!class_exists($modelClass)) {
            abort(404, 'Model não encontrado');
        }

        $model = App::make($modelClass);
        $query = $model::query()->whereIn('id', $ids);

        $modelName = class_basename($modelClass);
        $fileName  = Str::slug($modelName) . '-selected-' . now()->format('Y-m-d-His');

        if ($format === 'pdf') {
            return $this->exportPdf($query, $fileName, $modelName, $columns);
        }

        return $this->exportExcel($query, $fileName, $columns);
    }

    /**
     * Exporta para Excel
     */
    protected function exportExcel($query, string $fileName, array $columns = [])
    {
        return Excel::download(
            new CrudExport($query, $columns),
            $fileName . '.xlsx'
        );
    }

    /**
     * Exporta para PDF
     */
    protected function exportPdf($query, string $fileName, string $modelName, array $columns = [])
    {
        $data = $query->get();
        
        if ($data->isEmpty()) {
            abort(404, 'Nenhum registro encontrado para exportar');
        }

        return Pdf::view('ptah::exports.pdf', [
                'data'      => $data,
                'columns'   => $columns,
                'modelName' => $modelName,
                'date'      => now()->format('d/m/Y H:i:s'),
            ])
            ->format('a4')
            ->name($fileName . '.pdf');
    }

    /**
     * Aplica filtros na query
     */
    protected function applyFilters($query, array $filters): void
    {
        // Aqui você pode reutilizar a lógica de FilterService
        // Por simplicidade, vou aplicar filtros básicos
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                // Filtro com operador
                $operator = $value['operator'] ?? '=';
                $val      = $value['value'] ?? null;

                if ($val !== null) {
                    if ($operator === 'LIKE') {
                        $query->where($field, 'LIKE', '%' . $val . '%');
                    } else {
                        $query->where($field, $operator, $val);
                    }
                }
            } else {
                // Filtro simples (valor direto)
                $query->where($field, $value);
            }
        }
    }
}
