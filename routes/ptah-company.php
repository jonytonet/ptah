<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Company\CompanyList;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-companies', CompanyList::class)->name('ptah.company.index');
});
