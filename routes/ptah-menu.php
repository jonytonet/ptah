<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Menu\MenuList;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-menu', MenuList::class)->name('ptah.menu.manage');
});
