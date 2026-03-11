{{-- resources/views/livewire/auth/profile.blade.php --}}
<div>
    <x-forge-page-header :title="__('ptah::ui.profile_title')" :subtitle="__('ptah::ui.profile_subtitle')" />

    {{-- Tabs --}}
    <x-forge-tabs>
        <x-slot name="tabs">
            <x-forge-tab key="profile"      :active="$activeTab === 'profile'"      wire:click="$set('activeTab','profile')">{{ __('ptah::ui.profile_tab_profile') }}</x-forge-tab>
            <x-forge-tab key="password"     :active="$activeTab === 'password'"     wire:click="$set('activeTab','password')">{{ __('ptah::ui.profile_tab_password') }}</x-forge-tab>
            <x-forge-tab key="two_factor"   :active="$activeTab === 'two_factor'"   wire:click="$set('activeTab','two_factor')">{{ __('ptah::ui.profile_tab_2fa') }}</x-forge-tab>
            <x-forge-tab key="sessions"     :active="$activeTab === 'sessions'"     wire:click="$set('activeTab','sessions'); $wire.loadSessions()">{{ __('ptah::ui.profile_tab_sessions') }}</x-forge-tab>
            <x-forge-tab key="photo"        :active="$activeTab === 'photo'"        wire:click="$set('activeTab','photo')">{{ __('ptah::ui.profile_tab_photo') }}</x-forge-tab>
        </x-slot>

        {{-- ── ABA: PERFIL ── --}}
        @if ($activeTab === 'profile')
        <x-forge-card>
            @if ($successMsg)
                <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
            @endif

            <form wire:submit="saveProfile" class="space-y-5 max-w-lg">
                <x-forge-input name="name"  :label="__('ptah::ui.profile_name')"   wire:model="name"  :error="$errors->first('name')"  required />
                <x-forge-input name="email" type="email" label="E-mail" wire:model="email" :error="$errors->first('email')" required />

                <x-forge-button type="submit" color="primary">{{ __('ptah::ui.profile_save_btn') }}</x-forge-button>
            </form>
        </x-forge-card>
        @endif

        {{-- ── ABA: SENHA ── --}}
        @if ($activeTab === 'password')
        <x-forge-card>
            @if ($successMsg)
                <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
            @endif
            @if ($errorMsg)
                <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
            @endif

            <form wire:submit="savePassword" class="space-y-5 max-w-lg">
                <x-forge-input name="current_password" type="password" :label="__('ptah::ui.profile_current_pw')"
                    wire:model="current_password" :error="$errors->first('current_password')" required />
                <x-forge-input name="password" type="password" :label="__('ptah::ui.profile_new_pw')"
                    wire:model="password" :error="$errors->first('password')" required />
                <x-forge-input name="password_confirmation" type="password" :label="__('ptah::ui.profile_confirm_pw')"
                    wire:model="password_confirmation" required />

                <x-forge-button type="submit" color="primary">{{ __('ptah::ui.profile_change_pw_btn') }}</x-forge-button>
            </form>
        </x-forge-card>
        @endif

        {{-- ── ABA: 2FA ── --}}
        @if ($activeTab === 'two_factor')
        <x-forge-card>
            @if ($successMsg)
                <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
            @endif
            @if ($errorMsg)
                <x-forge-alert type="danger" class="mb-4">{{ $errorMsg }}</x-forge-alert>
            @endif

            {{-- Habilitar −−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−−--}}
            @if (!auth()->user()->two_factor_confirmed_at)
                <div class="max-w-lg">
                    <p class="text-gray-600 mb-5">
                        {{ __('ptah::ui.profile_2fa_intro') }}
                    </p>

                    {{-- Método TOTP --}}
                    <x-forge-card flat class="mb-4">
                        <div class="flex items-start gap-3 mb-3">
                            <div class="w-9 h-9 rounded-md bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-dark text-sm">Authenticator App (TOTP)</p>
                                <p class="text-xs text-gray-500">{{ __('ptah::ui.profile_totp_apps') }}</p>
                            </div>
                        </div>

                        @if ($showSetup2fa && $totpType === 'totp')
                            {{-- QR Code setup --}}
                            <div class="space-y-4">
                                <p class="text-sm text-gray-600">{{ __('ptah::ui.profile_scan_qr') }}</p>
                                <div class="flex justify-center p-3 bg-white rounded-md border border-gray-200 w-fit mx-auto">
                                    <img src="{{ $qrCodeSvg }}" alt="QR Code 2FA" class="w-[200px] h-[200px]">
                                </div>
                                <p class="text-xs text-gray-500 text-center">
                                    {{ __('ptah::ui.profile_enter_key') }} <code class="font-mono bg-gray-100 dark:bg-dark-3 px-1 py-0.5 rounded text-xs">{{ $totpSecret }}</code>
                                </p>
                                <form wire:submit="confirmTotp" class="flex gap-2">
                                    <x-forge-input name="totp_code" wire:model="totp_code" placeholder="000000"
                                        class="flex-1" :error="$errors->first('totp_code')" />
                                    <x-forge-button type="submit" color="primary">{{ __('ptah::ui.profile_confirm_btn') }}</x-forge-button>
                                </form>
                            </div>
                        @else
                            <x-forge-button wire:click="initTotp" color="secondary" size="sm">{{ __('ptah::ui.profile_setup_btn') }}</x-forge-button>
                        @endif
                    </x-forge-card>

                    {{-- Método E-mail --}}
                    <x-forge-card flat>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-md bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-dark text-sm">E-mail</p>
                                    <p class="text-xs text-gray-500">{{ __('ptah::ui.profile_email_code_hint', ['email' => auth()->user()->email]) }}</p>
                                </div>
                            </div>
                            <x-forge-button wire:click="enableEmailTwoFactor" color="secondary" size="sm">{{ __('ptah::ui.profile_enable_btn') }}</x-forge-button>
                        </div>
                    </x-forge-card>
                </div>
            @else
                {{-- 2FA já ativo --}}
                <div class="max-w-lg space-y-5">
                    <x-forge-alert type="success">
                        {{ __('ptah::ui.profile_2fa_active_label') }}
                        ({{ auth()->user()->two_factor_type === 'totp' ? __('ptah::ui.profile_2fa_authenticator') : 'E-mail' }}).
                    </x-forge-alert>

                    {{-- Códigos de recuperação --}}
                    @if ($recoveryCodes)
                    <div>
                        <p class="font-semibold text-sm text-dark mb-2">{{ __('ptah::ui.profile_recovery_codes_title') }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ __('ptah::ui.profile_recovery_codes_hint') }}</p>
                        <div class="grid grid-cols-2 gap-1 font-mono text-sm bg-gray-50 dark:bg-dark-3 p-3 rounded-md">
                            @foreach ($recoveryCodes as $rc)
                                <span>{{ $rc }}</span>
                            @endforeach
                        </div>
                        <x-forge-button wire:click="regenerateRecoveryCodes" color="secondary" size="sm" class="mt-3">
                            {{ __('ptah::ui.profile_regenerate_btn') }}
                        </x-forge-button>
                    </div>
                    @else
                    <x-forge-button wire:click="loadRecoveryCodes" color="secondary" size="sm">
                        {{ __('ptah::ui.profile_view_recovery_btn') }}
                    </x-forge-button>
                    @endif

                    <x-forge-button wire:click="disableTwoFactor" color="danger" wire:confirm="{{ __('ptah::ui.profile_disable_2fa_confirm') }}">
                        {{ __('ptah::ui.profile_disable_2fa_btn') }}
                    </x-forge-button>
                </div>
            @endif
        </x-forge-card>
        @endif

        {{-- ── ABA: SESSÕES ── --}}
        @if ($activeTab === 'sessions')
        <x-forge-card>
            @if ($successMsg)
                <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
            @endif

            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-600">{{ __('ptah::ui.profile_sessions_intro') }}</p>
                <x-forge-button wire:click="revokeOtherSessions" color="danger" size="sm"
                    wire:confirm="{{ __('ptah::ui.profile_disconnect_confirm') }}">
                    {{ __('ptah::ui.profile_disconnect_others') }}
                </x-forge-button>
            </div>

            @if (empty($sessions))
                <p class="text-sm text-gray-400 text-center py-6">{{ __('ptah::ui.profile_no_sessions') }}</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-dark-3">
                    @foreach ($sessions as $session)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gray-100 dark:bg-dark-3 flex items-center justify-center text-gray-400">
                                @if (str_contains(strtolower($session['platform'] ?? ''), 'mobile') || in_array($session['platform'] ?? '', ['Android','iPhone','iPad']))
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-medium text-dark">
                                    {{ $session['browser'] ?? __('ptah::ui.profile_unknown_browser') }}
                                    @if($session['is_current'])
                                        <span class="ml-1 text-xs text-green-600 font-semibold">({{ __('ptah::ui.profile_this_session') }})</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $session['platform'] ?? '' }} · {{ $session['ip_address'] ?? '' }}
                                    · {{ __('ptah::ui.profile_last_activity') }} {{ $session['last_activity_human'] ?? '' }}
                                </p>
                            </div>
                        </div>
                        @if (!$session['is_current'])
                            <x-forge-button wire:click="revokeSession('{{ $session['id'] }}')" color="secondary" size="xs">
                                {{ __('ptah::ui.profile_revoke_btn') }}
                            </x-forge-button>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </x-forge-card>
        @endif

        {{-- ── ABA: FOTO ── --}}
        @if ($activeTab === 'photo')
        <x-forge-card>
            @if ($successMsg)
                <x-forge-alert type="success" class="mb-4">{{ $successMsg }}</x-forge-alert>
            @endif

            <div class="flex flex-col items-center gap-5 max-w-xs mx-auto">
                {{-- Preview atual --}}
                <div class="relative w-24 h-24">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="preview"
                             class="w-24 h-24 rounded-full object-cover ring-2 ring-primary ring-offset-2">
                    @elseif (auth()->user()->profile_photo_path)
                        <img src="{{ Storage::url(auth()->user()->profile_photo_path) }}" alt="foto"
                             class="w-24 h-24 rounded-full object-cover ring-2 ring-primary ring-offset-2">
                    @else
                        <div class="w-24 h-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold ring-2 ring-primary ring-offset-2">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="w-full space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-dark mb-1">{{ __('ptah::ui.profile_select_image') }}</label>
                        <input type="file" wire:model="photo" accept="image/*"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                        @error('photo') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex gap-2">
                        <x-forge-button wire:click="savePhoto" color="primary" wire:loading.attr="disabled" class="flex-1">
                            <span wire:loading.remove wire:target="savePhoto">{{ __('ptah::ui.profile_save_photo_btn') }}</span>
                            <span wire:loading wire:target="savePhoto" class="flex items-center justify-center gap-2">
                                <x-forge-spinner size="sm" /> {{ __('ptah::ui.profile_saving') }}
                            </span>
                        </x-forge-button>

                        @if (auth()->user()->profile_photo_path)
                            <x-forge-button wire:click="removePhoto" color="secondary"
                                wire:confirm="{{ __('ptah::ui.profile_remove_confirm') }}">
                                {{ __('ptah::ui.profile_remove_btn') }}
                            </x-forge-button>
                        @endif
                    </div>
                </div>
            </div>
        </x-forge-card>
        @endif
    </x-forge-tabs>
</div>

