<?php

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\ExportController;

/*
|--------------------------------------------------------------------------
| Rotas Ptah — Exportação
|--------------------------------------------------------------------------
|
| Rotas para exportação de dados do BaseCrud (Excel e PDF)
|
*/

Route::middleware(['web'])->prefix('ptah')->name('ptah.')->group(function () {
    
    // Exportação com filtros
    Route::get('/export', [ExportController::class, 'export'])
        ->name('export');
    
    // Exportação em massa (itens selecionados)
    Route::get('/export/bulk', [ExportController::class, 'bulkExport'])
        ->name('export.bulk');
        
});

/*
|--------------------------------------------------------------------------
| Ptah Forge — Demo de componentes
|--------------------------------------------------------------------------
| Adicione esta rota no seu routes/web.php durante o desenvolvimento:
|
|   Route::get('/ptah-forge-demo', fn() => view('ptah::forge-demo'))
|        ->name('ptah.forge.demo');
|
| Remova em produção ou proteja com middleware de auth/admin.
*/

