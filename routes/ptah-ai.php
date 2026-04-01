<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\AI\AiModelConfigList;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-ai/models', AiModelConfigList::class)->name('ptah.ai.models');
});
