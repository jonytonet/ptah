<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Support\StackPage;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-companies', StackPage::action('companies', CompanyList::class, 'structure'))->name('ptah.company.index');
});
