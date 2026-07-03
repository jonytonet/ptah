<?php

namespace Ptah\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Ptah\Exports\CrudExport;
use Ptah\Models\CrudConfig;

class ExportController
{
    /**
     * Generates the export file from a snapshot the BaseCrud component built.
     *
     * The component (export/bulkExport) filters the listing through the shared
     * buildBaseQuery(), collects the ordered ids and caches them under a one-time,
     * user-scoped token. This controller only fetches those ids — so it can never
     * diverge from the listing and the client never names a model or reapplies
     * filters (the old ?model=User + naive applyFilters holes are gone).
     */
    public function download(string $token)
    {
        $payload = Cache::get('ptah:export:'.$token);

        if (! is_array($payload)) {
            abort(404);
        }

        // Snapshot is bound to the user who generated it (null = public listing).
        $owner = $payload['userId'] ?? null;
        if ($owner !== null && $owner !== Auth::id()) {
            abort(403);
        }

        $modelParam = (string) ($payload['model'] ?? '');

        // Defence in depth: still require the model to be a configured Ptah CRUD
        // (and pass the read permission when the module is active).
        $this->authorizeExport($modelParam);

        $modelClass = $this->resolveModelClass($modelParam);
        if (! $modelClass) {
            abort(404, 'Model não encontrado');
        }

        $model = App::make($modelClass);
        $pk = $model->getKeyName();
        $ids = $payload['ids'] ?? [];
        $columns = $payload['columns'] ?? [];
        $format = $payload['format'] ?? 'excel';

        $query = $model::query()->whereIn($pk, $ids);

        // Re-apply the listing's primary sort when it is a real column (relation
        // sorts degrade to primary-key order — see docs).
        $order = (string) ($payload['order'] ?? $pk);
        $direction = strtoupper((string) ($payload['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if (Schema::hasColumn($model->getTable(), $order)) {
            $query->orderBy($order, $direction);
        }

        $modelName = class_basename($modelClass);
        $fileName = Str::slug($modelName).'-'.now()->format('Y-m-d-His');

        if ($format === 'pdf') {
            return $this->exportPdf($query, $fileName, $modelName, $columns);
        }

        return $this->exportExcel($query, $fileName, $columns);
    }

    /**
     * Guards the export:
     *
     *  1. Allowlist — the model must actually be configured as a Ptah CRUD
     *     (has a crud_configs row). Blocks exporting arbitrary models.
     *  2. Permission — when the permissions module is active and the CRUD declares
     *     a permissionIdentifier, the user must pass ptah_can(..., 'read').
     *
     * Aborts 403 when either check fails.
     */
    protected function authorizeExport(string $modelParam): void
    {
        if ($modelParam === '') {
            abort(403, 'Export not allowed.');
        }

        /** @var CrudConfig|null $config */
        $config = CrudConfig::query()->where('model', $modelParam)->first();

        if (! $config) {
            abort(403, 'Export not allowed for this model.');
        }

        // Enforce the CRUD's read permission only when the module is active
        // (default install has it off — no behavioural change there).
        if (config('ptah.modules.permissions')) {
            $permKey = $config->config['permissions']['permissionIdentifier'] ?? null;

            if ($permKey && function_exists('ptah_can') && ! ptah_can($permKey, 'read')) {
                abort(403, 'You are not allowed to export this data.');
            }
        }
    }

    /**
     * Exporta para Excel
     */
    protected function exportExcel($query, string $fileName, array $columns = [])
    {
        return Excel::download(
            new CrudExport($query, $columns),
            $fileName.'.xlsx'
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

        // Buscar totalizadores se configurados
        $totalizers = $this->getTotalizers($query, $modelName);

        return Pdf::loadView('ptah::exports.pdf', [
            'data' => $data,
            'columns' => $columns,
            'modelName' => $modelName,
            'date' => now()->format('d/m/Y H:i:s'),
            'totalizers' => $totalizers,
        ])
            ->setPaper('a4', 'portrait')
            ->download($fileName.'.pdf');
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
            'App\\Models\\'.$class,                       // Laravel padrão
            app()->getNamespace().'Models\\'.$class,    // Namespace customizado
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Busca totalizadores configurados no CrudConfig
     */
    protected function getTotalizers($query, string $modelName): array
    {
        try {
            // Buscar configuração do CRUD
            $crudConfig = CrudConfig::where('model_name', $modelName)->first();

            if (! $crudConfig) {
                return [];
            }

            $config = $crudConfig->config ?? [];
            $totConfig = $config['totalizadores'] ?? [];

            // Verificar se totalizadores estão habilitados e tem colunas
            if (empty($totConfig['enabled']) || empty($totConfig['columns'])) {
                return [];
            }

            // Verificar se está visível na UI (opcional - sempre mostrar no PDF se configurado)
            $uiConfig = $config['ui'] ?? [];
            if (! ($uiConfig['showTotalizador'] ?? false)) {
                return [];
            }

            $result = [];

            // Calcular cada totalizador
            foreach ($totConfig['columns'] as $totCol) {
                $field = $totCol['field'] ?? null;
                $aggregate = $totCol['aggregate'] ?? 'sum';
                $label = $totCol['label'] ?? ucwords(str_replace('_', ' ', $field));

                if (! $field) {
                    continue;
                }

                // Clonar query para cada agregação
                $cloned = clone $query;

                $value = match ($aggregate) {
                    'sum' => $cloned->sum($field),
                    'count' => $cloned->count($field),
                    'avg' => round((float) $cloned->avg($field), 2),
                    'max' => $cloned->max($field),
                    'min' => $cloned->min($field),
                    default => null,
                };

                $result[] = [
                    'field' => $field,
                    'label' => $label,
                    'aggregate' => $aggregate,
                    'value' => $value,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            // Se houver erro, retornar array vazio
            return [];
        }
    }
}
