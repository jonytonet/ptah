{{-- resources/views/livewire/auth/reset-password.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">Nova senha</h2>
        <p class="text-sm text-gray-500 mt-1">Digite e confirme sua nova senha</p>
    </div>

    @if ($errorMsg)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
    @endif

    <form wire:submit="resetPassword" class="space-y-5">
        <x-forge-input
            name="email"
            type="email"
            label="E-mail"
            wire:model="email"
            :error="$errors->first('email')"
            required
        />

        <x-forge-input
            name="password"
            type="password"
            label="Nova senha"
            wire:model="password"
            :error="$errors->first('password')"
            required
        />

        <x-forge-input
            name="password_confirmation"
            type="password"
            label="Confirmar nova senha"
            wire:model="password_confirmation"
            required
        />

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Redefinir senha</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> Salvando...
            </span>
        </x-forge-button>
    </form>
</div>
