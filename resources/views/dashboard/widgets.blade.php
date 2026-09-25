{{-- ptah::dashboard.widgets — os widgets de config/ptah-dashboard.php.
     Pode ser incluído em qualquer página do host: @include('ptah::dashboard.widgets') --}}
@php
    $ptahWidgets = app(\Ptah\Services\DashboardService::class)->visibleWidgets();
    $ptahTone = fn (string $c) => ['primary' => 'var(--ptah-primary)', 'success' => 'var(--ptah-success-strong)', 'danger' => 'var(--ptah-danger-strong)', 'warn' => 'var(--ptah-warn-strong)'][$c] ?? 'var(--ptah-primary)';
@endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($ptahWidgets as $w)
        @php
            $wide = in_array($w['type'], ['trend', 'latest'], true);
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
                    <span class="text-2xl font-bold tabular-nums" style="color: {{ $ptahTone($w['color']) }}">{{ number_format($w['total'], 0, ',', '.') }}</span>
                    <span class="text-xs ptah-c-muted">{{ \Carbon\Carbon::parse($w['points'][0]['date'])->format('d/m') }} – {{ \Carbon\Carbon::parse(end($w['points'])['date'])->format('d/m') }}</span>
                </div>
                @php $n = count($w['points']); $bw = 100 / max(1, $n); @endphp
                <svg class="mt-3 w-full h-16" viewBox="0 0 100 40" preserveAspectRatio="none" role="img"
                    aria-label="{{ $w['label'] }}: {{ collect($w['points'])->map(fn ($p) => \Carbon\Carbon::parse($p['date'])->format('d/m').' '.$p['count'])->implode(', ') }}">
                    @foreach ($w['points'] as $i => $p)
                        @php $h = $p['count'] > 0 ? max(1.5, 38 * $p['count'] / $w['max']) : 0.6; @endphp
                        <rect x="{{ $i * $bw + $bw * 0.15 }}" y="{{ 40 - $h }}" width="{{ $bw * 0.7 }}" height="{{ $h }}"
                            style="fill: {{ $p['count'] > 0 ? $ptahTone($w['color']) : 'var(--ptah-line)' }}">
                            <title>{{ \Carbon\Carbon::parse($p['date'])->format('d/m') }}: {{ $p['count'] }}</title>
                        </rect>
                    @endforeach
                </svg>
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
