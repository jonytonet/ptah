<?php

namespace Ptah\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
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

        // Resolver a classe do model (suporta com/sem namespace)
        $modelClass = $this->resolveModelClass($modelClass);
        
        if (!$modelClass) {
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

        // Resolver a classe do model (suporta com/sem namespace)
        $modelClass = $this->resolveModelClass($modelClass);
        
        if (!$modelClass) {
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

        return Pdf::loadView('ptah::exports.pdf', [
                'data'      => $data,
                'columns'   => $columns,
                'modelName' => $modelName,
                'date'      => now()->format('d/m/Y H:i:s'),
            ])
            ->setPaper('a4', 'portrait')
            ->download($fileName . '.pdf');
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
    
    /**
     * Resolve o namespace completo da classe do model
     * Segue a mesma lógica do BaseCrud::resolveEloquentModel()
     */
    protected function resolveModelClass(string $modelName): ?string
    {
        // Converter barras para namespace (ex: "Purchase/Order" -> "Purchase\Order")
        $class = str_replace('/', '\\', $modelName);
        
        // Tentar vários prefixos conhecidos
        $candidates = [
            $class,                                          // Já vem com namespace completo
            'App\\Models\\' . $class,                       // Laravel padrão
            app()->getNamespace() . 'Models\\' . $class,    // Namespace customizado
        ];
        
        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }
        
        return null;
    }
}
