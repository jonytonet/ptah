<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.45;
            background: #f1f5f9;
            padding: 24px;
        }
        .sheet {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 28px 32px;
        }

        /* ── Toolbar (screen only) ── */
        .toolbar {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            max-width: 1100px;
            margin: 0 auto 16px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155;
            cursor: pointer;
            transition: background .15s, border-color .15s;
        }
        .btn:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-primary { background: #0f172a; color: #fff; border-color: #0f172a; }
        .btn-primary:hover { background: #1e293b; }

        /* ── Header ── */
        .doc-header { border-bottom: 2px solid #cbd5e1; padding-bottom: 14px; margin-bottom: 18px; }
        .doc-header h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 6px; }
        .doc-meta { font-size: 11px; color: #64748b; display: flex; flex-wrap: wrap; gap: 4px 18px; }
        .doc-meta strong { color: #334155; font-weight: 600; }
        .doc-filters { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 11px; padding: 2px 8px; border-radius: 9999px;
            background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;
        }
        .chip b { color: #0f172a; font-weight: 600; }
        .truncated-note {
            margin-top: 10px; font-size: 11px; color: #92400e;
            background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 6px 10px;
        }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table.print-table { width: 100%; border-collapse: collapse; }
        .print-table thead th {
            text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .02em; color: #0f172a; background: #f1f5f9;
            padding: 8px 10px; border-bottom: 2px solid #cbd5e1; white-space: nowrap;
        }
        .print-table tbody td {
            padding: 6px 10px; border-bottom: 1px solid #eef2f6; font-size: 12px; vertical-align: top;
        }
        .print-table tbody tr:nth-child(even) { background: #f8fafc; }
        .print-table tfoot td {
            padding: 9px 10px; font-weight: 700; color: #0f172a;
            border-top: 2px solid #cbd5e1; background: #f1f5f9; white-space: nowrap;
        }
        .text-end, .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-data { text-align: center; padding: 48px; color: #64748b; font-style: italic; }

        /* ── Utility-class shims (so formatCell badges/pills render without Tailwind) ── */
        [class*="inline-flex"] { display: inline-flex; align-items: center; }
        [class*="rounded-md"] { border-radius: 6px; }
        [class*="rounded-full"] { border-radius: 9999px; }
        [class*="px-2"] { padding-left: 8px; padding-right: 8px; }
        [class*="py-0.5"] { padding-top: 1px; padding-bottom: 1px; }
        [class*="text-xs"] { font-size: 11px; }
        [class*="font-medium"] { font-weight: 500; }
        [class*="font-semibold"] { font-weight: 600; }
        [class*="bg-green-50"]  { background: #f0fdf4; } [class*="text-green-700"]  { color: #15803d; }
        [class*="bg-yellow-50"] { background: #fefce8; } [class*="text-yellow-800"] { color: #854d0e; }
        [class*="bg-red-50"]    { background: #fef2f2; } [class*="text-red-700"]    { color: #b91c1c; }
        [class*="bg-blue-50"]   { background: #eff6ff; } [class*="text-blue-700"]   { color: #1d4ed8; }
        [class*="bg-indigo-50"] { background: #eef2ff; } [class*="text-indigo-700"] { color: #4338ca; }
        [class*="bg-purple-50"] { background: #faf5ff; } [class*="text-purple-700"] { color: #7e22ce; }
        [class*="bg-pink-50"]   { background: #fdf2f8; } [class*="text-pink-700"]   { color: #be185d; }
        [class*="bg-gray-50"]   { background: #f9fafb; } [class*="text-gray-600"]   { color: #4b5563; }
        [class*="ring-1"] { box-shadow: inset 0 0 0 1px rgba(100,116,139,.25); }
        .print-table a { color: #1d4ed8; text-decoration: none; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; }

        /* ── Print media ── */
        @media print {
            body { background: #fff; padding: 0; font-size: 11px; }
            .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; }
            .no-print { display: none !important; }
            .print-table thead { display: table-header-group; }
            .print-table tbody tr { page-break-inside: avoid; }
            .print-table tbody tr:nth-child(even) { background: #f8fafc !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            a { color: #1e293b; }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <button type="button" class="btn" onclick="ptahCopyTable()" id="ptah-copy-btn">
            📋 {{ __('ptah::ui.print_btn_copy') }}
        </button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            🖨 {{ __('ptah::ui.print_btn_print') }}
        </button>
        <button type="button" class="btn" onclick="window.close()">
            {{ __('ptah::ui.print_btn_close') }}
        </button>
    </div>

    <div class="sheet">
        <div class="doc-header">
            <h1>{{ $title }}</h1>
            <div class="doc-meta">
                <span><strong>{{ __('ptah::ui.print_generated_at') }}:</strong> {{ $generatedAt }}</span>
                <span><strong>{{ __('ptah::ui.print_total_records') }}:</strong> {{ number_format($totalRecords, 0, ',', '.') }}</span>
            </div>

            <div class="doc-filters">
                @forelse ($filters as $f)
                    <span class="chip"><b>{{ $f['label'] }}:</b> {{ \Illuminate\Support\Str::limit($f['value'], 40) }}</span>
                @empty
                    <span class="chip">{{ __('ptah::ui.print_no_filters') }}</span>
                @endforelse
            </div>

            @if ($truncated)
                <div class="truncated-note">{{ __('ptah::ui.print_truncated', ['n' => number_format($maxRows, 0, ',', '.')]) }}</div>
            @endif
        </div>

        @if (count($rows) > 0)
            <div class="table-wrap">
                <table class="print-table" id="ptah-print-table">
                    <thead>
                        <tr>
                            @foreach ($columns as $col)
                                <th class="{{ $col['align'] }}">{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr @if (!empty($row['style'])) style="{{ $row['style'] }}" @endif>
                                @foreach ($row['cells'] as $i => $cell)
                                    <td class="{{ $columns[$i]['align'] ?? 'text-start' }}">{!! $cell !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($hasTotals)
                        <tfoot>
                            <tr>
                                @php $labelPlaced = false; @endphp
                                @foreach ($columns as $col)
                                    @if ($col['total'] !== null)
                                        <td class="{{ $col['align'] }}">{{ $col['total'] }}</td>
                                    @elseif (! $labelPlaced)
                                        @php $labelPlaced = true; @endphp
                                        <td>{{ __('ptah::ui.print_totals_label') }}</td>
                                    @else
                                        <td></td>
                                    @endif
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @else
            <div class="no-data">{{ __('ptah::ui.print_no_data') }}</div>
        @endif
    </div>

    <script>
        // Copies the rendered table to the clipboard in two flavours:
        //  - text/html  → pastes into Excel / Google Sheets as a real table (columns split)
        //  - text/plain → TSV fallback for plain editors
        function ptahCopyTable() {
            var table = document.getElementById('ptah-print-table');
            var btn = document.getElementById('ptah-copy-btn');
            if (!table) { return; }

            var html = table.outerHTML;
            var tsv = Array.from(table.querySelectorAll('tr')).map(function (tr) {
                return Array.from(tr.querySelectorAll('th,td')).map(function (cell) {
                    return (cell.innerText || '').replace(/\s+/g, ' ').trim();
                }).join('\t');
            }).join('\n');

            var done = function (ok) {
                var original = btn.dataset.label || btn.innerHTML;
                btn.dataset.label = original;
                btn.innerHTML = ok
                    ? '✅ ' + @json(__('ptah::ui.print_copy_success'))
                    : '⚠️ ' + @json(__('ptah::ui.print_copy_failed'));
                setTimeout(function () { btn.innerHTML = original; }, 2000);
            };

            if (navigator.clipboard && window.ClipboardItem) {
                var item = new ClipboardItem({
                    'text/html': new Blob([html], { type: 'text/html' }),
                    'text/plain': new Blob([tsv], { type: 'text/plain' })
                });
                navigator.clipboard.write([item]).then(function () { done(true); }, function () { ptahCopyFallback(table, done); });
            } else {
                ptahCopyFallback(table, done);
            }
        }

        // Legacy fallback: select the table node and execCommand('copy') (copies rich text).
        function ptahCopyFallback(table, done) {
            try {
                var range = document.createRange();
                range.selectNode(table);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                var ok = document.execCommand('copy');
                sel.removeAllRanges();
                done(ok);
            } catch (e) {
                done(false);
            }
        }
    </script>
</body>
</html>
