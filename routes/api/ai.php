<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ptah\Http\Controllers\Admin\AiApiController;

/*
|--------------------------------------------------------------------------
| AI agent module — admin API for the React and Vue stacks (1.59.0)
|--------------------------------------------------------------------------
| The provider configuration screen (/ptah-ai/models, routes/ptah-ai.php) and
| the floating chat the dashboard layout mounts. Both Livewire entry points
| require a signed-in user (the route's ['web', 'auth']; the layout mounts the
| chat only under auth()->check()), so `auth` is added when the host's API
| middleware has none. Who may manage providers is decided by the component's
| own `ai.config` lock (boot() and every action), which runs here too.
*/

if (config('ptah.modules.ai_agent')) {
    $ptahAiApiMiddleware = (array) config('ptah.crud_api.middleware', ['web', 'auth']);

    $ptahAiApiHasAuth = false;
    foreach ($ptahAiApiMiddleware as $ptahAiApiEntry) {
        $ptahAiApiHasAuth = $ptahAiApiHasAuth || (is_string($ptahAiApiEntry) && str_starts_with($ptahAiApiEntry, 'auth'));
    }
    if (! $ptahAiApiHasAuth) {
        $ptahAiApiMiddleware[] = 'auth';
    }

    Route::middleware($ptahAiApiMiddleware)
        ->prefix(trim((string) config('ptah.crud_api.prefix', 'ptah/api'), '/').'/admin/ai')
        ->name('ptah.api.ai.')
        ->where(['model' => '[0-9]+', 'conversation' => '[0-9]+'])
        ->group(function () {
            // AiModelConfigList: ptah_can('ai.config', 'read') or master, checked by the component.
            Route::get('models', [AiApiController::class, 'models'])->name('models.index');
            Route::post('models', [AiApiController::class, 'storeModel'])->name('models.store');
            Route::get('models/{model}', [AiApiController::class, 'model'])->name('models.show');
            Route::put('models/{model}', [AiApiController::class, 'updateModel'])->name('models.update');
            Route::delete('models/{model}', [AiApiController::class, 'destroyModel'])->name('models.destroy');
            Route::post('models/{model}/default', [AiApiController::class, 'defaultModel'])->name('models.default');

            // AiChatWidget: any signed-in user while an active provider exists.
            Route::get('chat', [AiApiController::class, 'chat'])->name('chat');
            Route::get('chat/conversations', [AiApiController::class, 'conversations'])->name('chat.conversations');
            Route::post('chat/conversations', [AiApiController::class, 'newConversation'])->name('chat.conversations.new');
            Route::get('chat/conversations/{conversation}', [AiApiController::class, 'conversation'])->name('chat.conversations.show');
            Route::post('chat/messages', [AiApiController::class, 'send'])->name('chat.send');
            Route::post('chat/confirmations', [AiApiController::class, 'confirmation'])->name('chat.confirmation');
        });

    unset($ptahAiApiMiddleware, $ptahAiApiHasAuth, $ptahAiApiEntry);
}
