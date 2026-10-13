{{-- Cartao de previa de uma tool confirmavel (1.45.0).
     Espera $card: token, tool, summary, columns, rows, total_rows, status.
     O payload que sera gravado NAO esta aqui: fica no servidor, sob o token.
     Os botoes chamam o componente direto — o modelo nao participa da gravacao. --}}
@php
    $cardToken = (string) ($card['token'] ?? '');
    // O token e alfanumerico (Str::random); qualquer outra coisa nao vira botao.
    $cardValid = preg_match('/^[A-Za-z0-9]{20,64}$/', $cardToken) === 1;
    $cardColumns = is_array($card['columns'] ?? null) ? $card['columns'] : [];
    $cardRows = is_array($card['rows'] ?? null) ? $card['rows'] : [];
    $cardStatus = (string) ($card['status'] ?? 'pending');
    $cardCell = fn (mixed $v): string => is_scalar($v) || $v === null ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE);
@endphp
<div class="mt-2 rounded-lg border p-2.5 text-xs" style="border-color: var(--ptah-line-strong); background: var(--ptah-surface)"
     data-ptah-confirmation="{{ $cardValid ? $cardToken : '' }}">
    <p class="font-semibold" style="color: var(--ptah-text)">{{ $card['summary'] ?? '' }}</p>

    @if($cardRows !== [] && $cardColumns !== [])
        <div class="mt-2 max-h-56 overflow-auto rounded border" style="border-color: var(--ptah-line)">
            <table class="w-full text-[11px] tabular-nums">
                <thead>
                    <tr style="background: var(--ptah-line)">
                        @foreach($cardColumns as $label)
                            <th class="px-2 py-1 text-left font-semibold whitespace-nowrap" style="color: var(--ptah-text)">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($cardRows as $row)
                        <tr style="border-top: 1px solid var(--ptah-line)">
                            @foreach(array_keys($cardColumns) as $key)
                                <td class="px-2 py-1 align-top ptah-c-chat_hint">{{ $cardCell(is_array($row) ? ($row[$key] ?? '') : '') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(($card['total_rows'] ?? 0) > count($cardRows))
            <p class="mt-1 ptah-c-chat_hint">{{ __('ptah::ui.ai_confirm_more_rows', ['shown' => count($cardRows), 'total' => $card['total_rows']]) }}</p>
        @endif
    @endif

    @if($cardStatus === 'pending' && $cardValid)
        <div class="mt-2 flex gap-2">
            <button type="button"
                    wire:click="confirmAction('{{ $cardToken }}')"
                    wire:loading.attr="disabled" wire:target="confirmAction,cancelAction"
                    class="rounded-md bg-primary px-3 py-1.5 font-semibold hover:bg-primary-dark disabled:opacity-50"
                    style="color: var(--ptah-text-on-accent)">
                {{ __('ptah::ui.ai_confirm_btn') }}
            </button>
            <button type="button"
                    wire:click="cancelAction('{{ $cardToken }}')"
                    wire:loading.attr="disabled" wire:target="confirmAction,cancelAction"
                    class="rounded-md border px-3 py-1.5 font-semibold disabled:opacity-50"
                    style="border-color: var(--ptah-line-strong); color: var(--ptah-text)">
                {{ __('ptah::ui.ai_cancel_btn') }}
            </button>
        </div>
    @else
        <p class="mt-2 font-semibold ptah-c-chat_hint" role="status">
            {{ __('ptah::ui.ai_confirm_status_'.(in_array($cardStatus, ['confirmed', 'cancelled', 'expired', 'failed'], true) ? $cardStatus : 'expired')) }}
        </p>
    @endif
</div>
