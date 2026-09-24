{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- ── Modal Importar Planilha (HasCrudImport) ─────────────────────────── --}}
{{-- Upload → colunas → revisão → concluído. Só tokens --ptah-* e forge-*:  --}}
{{-- esta tela nasce já no tema do usuário (claro, escuro, papel).          --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}
@if ($showImportModal)
    @php
        $importCols = $this->importFormCols();
        $importMax = $this->importMaxRows();
    @endphp
    <x-forge-modal wire:model="showImportModal" :title="__('ptah::ui.import_title')" size="lg">
        <div class="space-y-5">
            <x-forge-stepper :current-step="$importStep" :steps="[
                ['label' => __('ptah::ui.import_step_upload')],
                ['label' => __('ptah::ui.import_step_map')],
                ['label' => __('ptah::ui.import_step_preview')],
                ['label' => __('ptah::ui.import_step_done')],
            ]" />

            {{-- 1. Arquivo --}}
            @if ($importStep === 1)
                <div class="space-y-2">
                    <p class="text-sm ptah-c-muted">{{ __('ptah::ui.import_upload_hint', ['max' => $importMax]) }}</p>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-md border px-3 py-2 text-sm transition-colors ptah-c-btn">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        {{ __('ptah::ui.btn_import') }}
                        <input type="file" class="sr-only" wire:model="importFile" accept=".csv,.txt,.xlsx,.xls" />
                    </label>
                    <div wire:loading wire:target="importFile" class="text-xs ptah-c-muted">…</div>
                    @error('importFile')
                        <p class="text-xs ptah-c-field_err" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- 2. Colunas --}}
            @if ($importStep === 2)
                <p class="text-sm ptah-c-muted">{{ __('ptah::ui.import_map_hint') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($importHeaders as $index => $header)
                        <div wire:key="import-map-{{ $index }}">
                            <label for="import-map-{{ $index }}" class="block mb-1 text-xs font-semibold ptah-c-form_lbl">{{ $header !== '' ? $header : '#'.($index + 1) }}</label>
                            <select id="import-map-{{ $index }}" wire:model="importMapping.{{ $index }}"
                                class="w-full text-sm rounded-md px-2 py-2 ptah-c-fp_input ptah-c-control">
                                <option value="">{{ __('ptah::ui.import_ignore_column') }}</option>
                                @foreach ($importCols as $col)
                                    <option value="{{ $col['colsNomeFisico'] }}">{{ $col['colsNomeLogico'] ?? $col['colsNomeFisico'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 3. Revisão --}}
            @if ($importStep === 3)
                @php $pv = $importPreview; @endphp
                @if (!empty($pv['unmapped_required']))
                    <x-forge-alert color="danger">{{ __('ptah::ui.import_unmapped_required', ['fields' => implode(', ', $pv['unmapped_required'])]) }}</x-forge-alert>
                @endif

                <x-forge-alert :color="empty($pv['errors']) && empty($pv['unmapped_required']) ? 'success' : 'warn'">
                    {{ __('ptah::ui.import_preview_summary', ['valid' => $pv['valid'] ?? 0, 'total' => $pv['total'] ?? 0]) }}
                </x-forge-alert>

                @if (!empty($pv['errors']))
                    <div class="space-y-2">
                        <p class="text-sm font-semibold ptah-c-form_lbl">{{ __('ptah::ui.import_errors_title') }}</p>
                        <div class="overflow-x-auto rounded-md border" style="border-color: var(--ptah-line-strong)">
                            <table class="w-full text-sm">
                                <thead style="background: var(--ptah-panel); color: var(--ptah-text-secondary)">
                                    <tr>
                                        <th scope="col" class="px-3 py-2 text-left">{{ __('ptah::ui.import_line') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left">{{ __('ptah::ui.import_step_map') }}</th>
                                        <th scope="col" class="px-3 py-2 text-left"></th>
                                    </tr>
                                </thead>
                                <tbody style="color: var(--ptah-text)">
                                    @foreach ($pv['errors'] as $err)
                                        <tr style="border-top: 1px solid var(--ptah-line)">
                                            <td class="px-3 py-1.5 tabular-nums">{{ $err['line'] }}</td>
                                            <td class="px-3 py-1.5">{{ $err['field'] }}</td>
                                            <td class="px-3 py-1.5 ptah-c-field_err">{{ $err['message'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if (($pv['more_errors'] ?? 0) > 0)
                            <p class="text-xs ptah-c-muted">{{ __('ptah::ui.import_more_errors', ['count' => $pv['more_errors']]) }}</p>
                        @endif
                        <p class="text-xs ptah-c-muted">{{ __('ptah::ui.import_fix_and_retry') }}</p>
                    </div>
                @endif
            @endif

            {{-- 4. Concluído --}}
            @if ($importStep === 4)
                @if (!empty($importResult['error']))
                    <x-forge-alert color="danger">{{ $importResult['error'] }}</x-forge-alert>
                @else
                    <x-forge-alert color="success">{{ __('ptah::ui.import_done', ['created' => $importResult['created'] ?? 0, 'updated' => $importResult['updated'] ?? 0]) }}</x-forge-alert>
                @endif
            @endif
        </div>

        <x-slot name="footer">
            @if ($importStep === 2)
                <x-forge-button wire:click="openImport" color="dark" flat>{{ __('ptah::ui.import_back') }}</x-forge-button>
                <x-forge-button wire:click="previewImport" wire:loading.attr="disabled" color="primary">{{ __('ptah::ui.import_next') }}</x-forge-button>
            @elseif ($importStep === 3)
                <x-forge-button wire:click="$set('importStep', 2)" color="dark" flat>{{ __('ptah::ui.import_back') }}</x-forge-button>
                @if (empty($importPreview['errors']) && empty($importPreview['unmapped_required']) && ($importPreview['valid'] ?? 0) > 0)
                    <x-forge-button wire:click="runImport" wire:loading.attr="disabled" color="primary">{{ __('ptah::ui.import_run', ['count' => $importPreview['valid']]) }}</x-forge-button>
                @endif
            @else
                <x-forge-button wire:click="closeImport" color="dark" flat>{{ __('ptah::ui.modal_close') }}</x-forge-button>
            @endif
        </x-slot>
    </x-forge-modal>
@endif
