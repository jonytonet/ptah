{{-- resources/views/livewire/auth/login.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">Entrar na sua conta</h2>
        <p class="text-sm text-gray-500 mt-1">Bem-vindo de volta</p>
    </div>

    {{-- Status message (após reset de senha) --}}
    @if (session('status'))
        <x-forge-alert type="success" class="mb-4">{{ session('status') }}</x-forge-alert>
    @endif

    {{-- Error --}}
    @if ($errorMessage)
        <x-forge-alert type="danger" class="mb-4">{{ $errorMessage }}</x-forge-alert>
    @endif

    {{-- Formulário --}}
    <form wire:submit="login" class="space-y-5">
        <x-forge-input
            name="email"
            type="email"
            label="E-mail"
            wire:model="email"
            :error="$errors->first('email')"
            required
            autofocus
        />

        <x-forge-input
            name="password"
            type="password"
            label="Senha"
            wire:model="password"
            :error="$errors->first('password')"
            required
        />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 cursor-pointer text-gray-600">
                <input type="checkbox" wire:model="remember" class="rounded border-gray-300 text-primary focus:ring-primary/30">
                Lembrar-me
            </label>
            <a href="{{ route('ptah.auth.forgot-password') }}" class="text-primary hover:underline font-medium">
                Esqueceu a senha?
            </a>
        </div>

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>Entrar</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> Entrando...
            </span>
        </x-forge-button>
    </form>
</div>
