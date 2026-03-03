{{-- resources/views/livewire/auth/reset-password.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">{{ __('ptah::ui.reset_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('ptah::ui.reset_subtitle') }}</p>
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
            :label="__('ptah::ui.reset_new_password')"
            wire:model="password"
            :error="$errors->first('password')"
            required
        />

        <x-forge-input
            name="password_confirmation"
            type="password"
            :label="__('ptah::ui.reset_confirm_password')"
            wire:model="password_confirmation"
            required
        />

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('ptah::ui.reset_btn') }}</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> {{ __('ptah::ui.reset_btn_loading') }}
            </span>
        </x-forge-button>
    </form>
</div>
