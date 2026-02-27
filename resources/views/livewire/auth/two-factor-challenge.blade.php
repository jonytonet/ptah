{{-- resources/views/livewire/auth/two-factor-challenge.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">Verificação em duas etapas</h2>
        <p class="text-sm text-gray-500 mt-1">
            @if ($usingRecovery)
                Digite um dos seus códigos de recuperação
            @else
                Digite o código do seu aplicativo autenticador ou e-mail
            @endif
        </p>
    </div>

    @if (session('code_sent'))
        <x-forge-alert type="success" class="mb-4">{{ session('code_sent') }}</x-forge-alert>
    @endif

    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <form wire:submit="verify" class="space-y-5">
        <x-forge-input
            :name="$usingRecovery ? 'recovery_code' : 'code'"
            :type="$usingRecovery ? 'text' : 'text'"
            :label="$usingRecovery ? 'Código de recuperação' : 'Código de verificação'"
            wire:model="code"
            :error="$errors->first('code')"
            required
            autofocus
        />

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Verificar</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> Verificando...
            </span>
        </x-forge-button>
    </form>

    <div class="mt-5 flex flex-col items-center gap-2 text-sm">
        <button
            type="button"
            wire:click="$set('usingRecovery', {{ $usingRecovery ? 'false' : 'true' }})"
            class="text-primary hover:underline font-medium"
        >
            @if ($usingRecovery)
                Usar código do autenticador
            @else
                Usar código de recuperação
            @endif
        </button>

        <button
            type="button"
            wire:click="sendEmailCode"
            class="text-gray-500 hover:text-primary hover:underline"
        >
            Reenviar código por e-mail
        </button>

        <a href="{{ route('ptah.auth.login') }}" class="text-gray-400 hover:text-gray-600 hover:underline">
            Voltar ao login
        </a>
    </div>
</div>
