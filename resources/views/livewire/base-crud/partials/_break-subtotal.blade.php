{{-- ── Linha de subtotal de um grupo (quebra) ───────────────────────────
     Espera no escopo: $visibleCols, $effectivePerms, $breakSums, $breakCount,
     $crudConfig. Alinha os valores às mesmas colunas do totalizador. --}}
<tr class="ptah-c-break_subtotal" wire:key="break-subtotal-{{ md5(json_encode($breakSums)) }}-{{ $breakCount }}-{{ $loop->index ?? 'end' }}">
    @if ($effectivePerms['canDelete'])
        <td class="ptah-no-print"></td>
    @endif
    @foreach ($visibleCols as $stCol)
        @if (($stCol['colsTipo'] ?? '') !== 'action')
            @php
                $stField = $stCol['colsNomeFisico'] ?? '';
                $stVal   = $breakSums[$stField] ?? null;
            @endphp
            <td class="px-3 py-2 text-xs font-semibold whitespace-nowrap {{ $stCol['colsAlign'] ?? 'text-start' }}">
                @if ($loop->first && $stVal === null)
                    <span class="opacity-60">{{ __('ptah::ui.break_subtotal', ['n' => $breakCount]) }}</span>
                @elseif ($stVal !== null)
                    @if (($stCol['colsHelper'] ?? '') === 'currencyFormat')
                        {{ __('ptah::ui.currency_prefix') }}{{ number_format($stVal, 2, __('ptah::ui.number_dec_point'), __('ptah::ui.number_thousands')) }}
                    @else
                        {{ rtrim(rtrim(number_format($stVal, 2, __('ptah::ui.number_dec_point'), __('ptah::ui.number_thousands')), '0'), __('ptah::ui.number_dec_point')) }}
                    @endif
                @endif
            </td>
        @endif
    @endforeach
    @foreach ($visibleCols as $stCol)
        @if (($stCol['colsTipo'] ?? '') === 'action')
            <td></td>
        @endif
    @endforeach
    @if ($effectivePerms['canUpdate'] || $effectivePerms['canDelete'])
        <td class="ptah-no-print"></td>
    @endif
</tr>
