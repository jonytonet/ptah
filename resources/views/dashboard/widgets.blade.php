{{-- ptah::dashboard.widgets — os widgets de config/ptah-dashboard.php.
     Pode ser incluído em qualquer página do host: @include('ptah::dashboard.widgets'),
     ou um grupo nomeado: @include('ptah::dashboard.widgets', ['group' => 'financeiro']) --}}
@php
    $ptahWidgets = app(\Ptah\Services\DashboardService::class)->visibleWidgets(isset($group) && is_string($group) ? $group : null);
    $ptahTone = fn (string $c) => ['primary' => 'var(--ptah-primary)', 'success' => 'var(--ptah-success-strong)', 'danger' => 'var(--ptah-danger-strong)', 'warn' => 'var(--ptah-warn-strong)'][$c] ?? 'var(--ptah-primary)';
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($ptahWidgets as $w)
        @php
            $wide = in_array($w['type'], ['trend', 'latest', 'breakdown'], true);
            $tag = $w['link'] ? 'a' : 'div';
        @endphp
        <{{ $tag }} @if ($w['link']) href="{{ $w['link'] }}" @endif
            class="block rounded-md border p-4 transition-colors {{ $wide ? 'sm:col-span-2' : '' }} {{ $w['link'] ? 'hover:underline-offset-2' : '' }}"
            style="border-color: var(--ptah-line-strong); background: var(--ptah-surface)">
            <p class="text-xs font-semibold uppercase tracking-wide ptah-c-muted">{{ $w['label'] }}</p>

            @if ($w['error'])
                <p class="mt-2 text-xs ptah-c-field_err" role="alert">{{ $w['error'] }}</p>
            @elseif ($w['type'] === 'stat')
                <p class="mt-2 text-2xl font-bold tabular-nums" style="color: {{ $ptahTone($w['color']) }}">{{ $w['value'] }}</p>
            @elseif ($w['type'] === 'trend')
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-bold tabular-nums" style="color: {{ $ptahTone($w['color']) }}">{{ $w['display_total'] }}</span>
                    <span class="text-xs ptah-c-muted">{{ $w['points'][0]['label'] }} – {{ end($w['points'])['label'] }}</span>
                </div>
                @php $n = count($w['points']); $bw = 100 / max(1, $n); @endphp
                <svg class="mt-3 w-full h-16" viewBox="0 0 100 40" preserveAspectRatio="none" role="img"
                    aria-label="{{ $w['label'] }}: {{ collect($w['points'])->map(fn ($p) => $p['label'].' '.$p['display'])->implode(', ') }}">
                    @foreach ($w['points'] as $i => $p)
                        @php $h = $p['value'] > 0 ? max(1.5, 38 * $p['value'] / $w['max']) : 0.6; @endphp
                        <rect x="{{ $i * $bw + $bw * 0.15 }}" y="{{ 40 - $h }}" width="{{ $bw * 0.7 }}" height="{{ $h }}"
                            style="fill: {{ $p['value'] > 0 ? $ptahTone($w['color']) : 'var(--ptah-line)' }}">
                            <title>{{ $p['label'] }}: {{ $p['display'] }}</title>
                        </rect>
                    @endforeach
                </svg>
            @elseif ($w['type'] === 'breakdown')
                <p class="mt-2 text-2xl font-bold tabular-nums" style="color: {{ $ptahTone($w['color']) }}">{{ $w['display_total'] }}</p>
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    @forelse ($w['items'] as $item)
                        <li>
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate {{ ! empty($item['others']) ? 'ptah-c-muted' : '' }}" style="{{ empty($item['others']) ? 'color: var(--ptah-text)' : '' }}">{{ $item['label'] }}</span>
                                <span class="tabular-nums ptah-c-muted">{{ $item['display'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full rounded-full" style="background: var(--ptah-line)" aria-hidden="true">
                                <div class="h-1.5 rounded-full" style="width: {{ $item['value'] > 0 ? max(1, round(100 * $item['value'] / $w['max'], 1)) : 0 }}%; background: {{ ! empty($item['others']) ? 'var(--ptah-line-strong)' : $ptahTone($w['color']) }}"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-xs ptah-c-muted">—</li>
                    @endforelse
                </ul>
            @elseif ($w['type'] === 'latest')
                <table class="mt-2 w-full text-sm">
                    <tbody>
                        @forelse ($w['rows'] as $row)
                            <tr style="border-top: 1px solid var(--ptah-line)">
                                @foreach ($row as $j => $cell)
                                    <td class="py-1.5 pr-2 {{ $j === 0 ? 'font-medium' : 'ptah-c-muted' }}" style="{{ $j === 0 ? 'color: var(--ptah-text)' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="py-2 text-xs ptah-c-muted">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </{{ $tag }}>
    @endforeach
</div>
