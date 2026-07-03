<?php

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\CrudPrintController;
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

    // Download do export (lê o snapshot em cache gerado pelo BaseCrud::export/bulkExport:
    // ids já filtrados pela listagem; o model é resolvido no servidor, sem parâmetro do cliente).
    Route::get('/export/download/{token}', [ExportController::class, 'download'])
        ->name('export.download');

    // Tela de impressão (lê o snapshot em cache gerado pelo BaseCrud::printView)
    Route::get('/print/{token}', [CrudPrintController::class, 'print'])
        ->name('print');

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
