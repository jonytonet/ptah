<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\Settings\SettingsPage;

// Access is re-checked in SettingsPage::boot() (ptah_can_manage_structure).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-settings', SettingsPage::class)->name('ptah.settings');
});
