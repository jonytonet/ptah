<?php

use Illuminate\Support\Facades\Route;
use Ptah\Livewire\AI\AiModelConfigList;
use Ptah\Support\StackPage;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/ptah-ai/models', StackPage::action('ai-models', AiModelConfigList::class, 'ai'))->name('ptah.ai.models');
});
