{{-- resources/views/livewire/auth/two-factor-challenge.blade.php --}}
<div class="w-full">

    <div class="text-center mb-6">
        <h2 class="text-xl font-semibold text-dark">{{ __('ptah::ui.two_fa_page_title') }}</h2>
        <p class="text-sm text-gray-500 mt-1">
            @if ($usingRecovery)
                {{ __('ptah::ui.two_fa_recovery_subtitle') }}
            @else
                {{ __('ptah::ui.two_fa_auth_subtitle') }}
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
            :label="$usingRecovery ? __('ptah::ui.two_fa_recovery_code_label') : __('ptah::ui.two_fa_verification_label')"
            wire:model="code"
            :error="$errors->first('code')"
            required
            autofocus
        />

        <x-forge-button type="submit" color="primary" class="w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('ptah::ui.two_fa_verify_btn') }}</span>
            <span wire:loading class="flex items-center justify-center gap-2">
                <x-forge-spinner size="sm" /> {{ __('ptah::ui.two_fa_verifying') }}
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
                {{ __('ptah::ui.two_fa_use_authenticator') }}
            @else
                {{ __('ptah::ui.two_fa_use_recovery_code') }}
            @endif
        </button>

        <button
            type="button"
            wire:click="sendEmailCode"
            class="text-gray-500 hover:text-primary hover:underline"
        >
            {{ __('ptah::ui.two_fa_resend_email') }}
        </button>

        <a href="{{ route('ptah.auth.login') }}" class="text-gray-400 hover:text-gray-600 hover:underline">
                {{ __('ptah::ui.two_fa_back_login') }}
        </a>
    </div>
</div>
