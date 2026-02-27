{{-- resources/views/livewire/auth/forgot-password.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">Recuperar senha</h2>
        <p class="text-sm text-gray-500 mt-1">Enviaremos um link de redefinição para o seu e-mail</p>
    </div>

    @if ($status)
        <x-forge-alert type="success" class="mb-4">{{ $status }}</x-forge-alert>
    @endif

    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <form wire:submit="sendLink" class="space-y-5">
        <x-forge-input
            name="email"
            type="email"
            label="E-mail"
            wire:model="email"
            :error="$errors->first('email')"
            required
            autofocus
        />

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Enviar link de recuperação</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> Enviando...
            </span>
        </x-forge-button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
        Lembrou a senha?
        <a href="{{ route('ptah.auth.login') }}" class="text-primary hover:underline font-medium">Voltar ao login</a>
    </p>
</div>
