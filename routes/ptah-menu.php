<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Support\StackPage;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-menu', StackPage::action('menu', MenuList::class, 'structure'))->name('ptah.menu.manage');
});
