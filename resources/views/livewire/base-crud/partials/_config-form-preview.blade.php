{{-- ═══════════════════════════════════════════════════════════════════════
     Inert form preview — mirrors the real create/edit modal layout from the
     columns currently being configured (unsaved). Visual only: every control
     is disabled, no wire:model, no validation, no queries, no actions.
     ═══════════════════════════════════════════════════════════════════════ --}}
<div x-show="$wire.showPreview" x-cloak
     class="fixed inset-0 z-[70] flex items-center justify-center p-4"
     @keydown.escape.window="$wire.closePreview()">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40" @click="$wire.closePreview()"></div>

    {{-- Panel --}}
    <div x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="relative w-full max-w-2xl rounded-xl bg-white shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-base font-semibold text-slate-800">
                    {{ __('ptah::ui.cfg_preview_title') }}
                    @if ($displayName !== '')
                        <span class="text-slate-400 font-normal">— {{ $displayName }}</span>
                    @endif
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ __('ptah::ui.cfg_preview_inert_notice') }}</p>
            </div>
            <button type="button" @click="$wire.closePreview()"
                class="p-1.5 rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                aria-label="{{ __('ptah::ui.cfg_footer_cancel') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-6 py-5">
            @php $cols = $this->previewFormCols(); @endphp

            @if (empty($cols))
                <div class="py-12 text-center text-sm text-slate-400">
                    {{ __('ptah::ui.cfg_preview_empty') }}
                </div>
            @else
                <div class="flex flex-col gap-4">
                    @php $prevBlock = null; @endphp
                    @foreach ($cols as $col)
                        @php
                            $pField    = $col['colsNomeFisico'] ?? '';
                            $pLabel    = $col['colsNomeLogico'] ?? $pField;
                            $pTipo     = $col['colsTipo'] ?? 'text';
                            $pRequired = in_array($col['colsRequired'] ?? false, [true, 'S', 1, '1'], true);
                            $pHelp     = $col['colsHelpText'] ?? null;
                            $pBlock    = trim((string) ($col['colsFormBlock'] ?? ''));
                            $pDepends  = $col['colsSDDependsOn'] ?? null;
                            $pOnChange = ! empty($col['colsOnChange']);
                        @endphp

                        {{-- Section heading when colsFormBlock changes --}}
                        @if ($pBlock !== '' && $pBlock !== $prevBlock)
                            <div class="flex items-center gap-3 {{ $loop->first ? '' : 'mt-3' }}">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $pBlock }}</span>
                                <div class="flex-1 h-px bg-slate-100"></div>
                            </div>
                        @endif
                        @php $prevBlock = $pBlock; @endphp

                        <div>
                            <label class="block mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ $pLabel }}
                                @if ($pRequired)<span class="text-red-500 ml-0.5">*</span>@endif
                                @if ($pOnChange)
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-indigo-50 text-indigo-600 align-middle">⚡ {{ __('ptah::ui.cfg_preview_calc_badge') }}</span>
                                @endif
                            </label>

                            @switch($pTipo)
                                @case('select')
                                    <select disabled class="block w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm bg-slate-50 text-slate-400 cursor-not-allowed">
                                        <option>{{ __('ptah::ui.select_placeholder') }}</option>
                                        @foreach (($col['colsSelect'] ?? []) as $k => $v)
                                            <option>{{ is_string($k) ? $k : $v }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('searchdropdown')
                                    @php
                                        $parentLabel = $pDepends
                                            ? (collect($formEditFields)->firstWhere('colsNomeFisico', $pDepends)['colsNomeLogico'] ?? $pDepends)
                                            : null;
                                    @endphp
                                    <div class="relative">
                                        <input type="text" disabled
                                            placeholder="{{ $pDepends ? __('ptah::ui.sd_select_parent_first', ['parent' => $parentLabel]) : __('ptah::ui.search_entity', ['label' => $pLabel]) }}"
                                            class="block w-full rounded-md border border-slate-200 px-3 py-2.5 pr-9 text-sm bg-slate-50 text-slate-400 cursor-not-allowed" />
                                        <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                    @if ($pDepends)
                                        <p class="mt-1 text-[10px] text-indigo-500">🔗 {{ __('ptah::ui.cfg_preview_depends', ['parent' => $parentLabel]) }}</p>
                                    @endif
                                    @break

                                @case('textarea')
                                    <textarea disabled rows="3" class="block w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm bg-slate-50 text-slate-400 cursor-not-allowed resize-none"></textarea>
                                    @break

                                @case('boolean')
                                    <select disabled class="block w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm bg-slate-50 text-slate-400 cursor-not-allowed">
                                        <option>{{ __('ptah::ui.bool_yes') }}</option>
                                        <option>{{ __('ptah::ui.bool_no') }}</option>
                                    </select>
                                    @break

                                @case('image')
                                    <div class="flex items-center justify-center h-24 rounded-md border-2 border-dashed border-slate-200 bg-slate-50 text-slate-300">
                                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    @break

                                @default
                                    {{-- text / number / date / datetime / masked --}}
                                    <input type="{{ in_array($pTipo, ['date','datetime']) ? 'text' : ($pTipo === 'number' ? 'text' : 'text') }}" disabled
                                        placeholder="{{ $col['colsMask'] ?? '' }}"
                                        class="block w-full rounded-md border border-slate-200 px-3 py-2.5 text-sm bg-slate-50 text-slate-400 cursor-not-allowed" />
                            @endswitch

                            @if (! empty($pHelp))
                                <p class="mt-1 text-xs text-slate-400">{{ $pHelp }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Footer — preview has no real actions; the buttons are inert/illustrative --}}
        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 bg-slate-50/60">
            <span class="mr-auto text-[11px] text-slate-400">{{ __('ptah::ui.cfg_preview_footer_hint') }}</span>
            <span class="px-4 py-2 text-xs font-semibold rounded-md text-slate-400 bg-white border border-slate-200 cursor-not-allowed select-none">{{ __('ptah::ui.btn_cancel') }}</span>
            <span class="px-4 py-2 text-xs font-semibold rounded-md text-white bg-primary/50 cursor-not-allowed select-none">{{ __('ptah::ui.btn_create') }}</span>
        </div>
    </div>
</div>
