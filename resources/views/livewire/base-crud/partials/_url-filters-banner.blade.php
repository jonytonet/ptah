{{-- ── URL filters banner (?f[field]=value) ────────────────────────────────
     Shown while urlFilters is non-empty. Overrides saved preferences until
     the user touches the filter panel or clicks "Clear" (clearUrlFilters).
     role="status": the banner appears/disappears dynamically and must be
     announced to assistive tech. --}}
<x-forge-alert color="warn" role="status" class="mb-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="font-semibold">{{ __('ptah::ui.url_filters_active') }}</span>

        <div class="flex flex-wrap items-center gap-1.5">
            @foreach ($urlFilters as $field => $spec)
                @php
                    $col = collect($crudConfig['cols'] ?? [])->firstWhere('colsNomeFisico', $field);
                    $label = $col['colsNomeLogico'] ?? $field;
                    $rawVal = $spec['val'] ?? '';
                    // Defensive: never assume a flat scalar list — stringify
                    // only scalar items and drop anything else, so a residual
                    // nested structure can never blow up implode()/(string).
                    $items = is_array($rawVal) ? $rawVal : [$rawVal];
                    $value = collect($items)
                        ->filter(fn ($v) => is_scalar($v))
                        ->map(fn ($v) => (string) $v)
                        ->implode(', ');
                @endphp
                <span class="ptah-c-chip">
                    <span class="opacity-60 mr-0.5">{{ $label }}:</span>
                    {{ \Illuminate\Support\Str::limit($value, 30) }}
                </span>
            @endforeach
        </div>

        <button type="button" wire:click="clearUrlFilters"
            class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md border transition-colors ptah-c-btn">
            {{ __('ptah::ui.btn_clear_url_filters') }}
        </button>
    </div>
</x-forge-alert>
