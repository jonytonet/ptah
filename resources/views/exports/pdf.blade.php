<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportação - {{ $modelName }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            padding: 20px;
        }
        
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #64748b;
            padding-bottom: 10px;
        }
        
        .header h1 {
            font-size: 18px;
            color: #0f172a;
            margin-bottom: 5px;
        }
        
        .header .meta {
            font-size: 9px;
            color: #64748b;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        thead {
            background-color: #f1f5f9;
        }
        
        th {
            padding: 8px;
            text-align: left;
            font-size: 9px;
            font-weight: 600;
            color: #0f172a;
            border-bottom: 2px solid #cbd5e1;
            text-transform: uppercase;
        }
        
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        tbody tr:hover {
            background-color: #f1f5f9;
        }
        
        .totalizers {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }
        
        .totalizers h3 {
            font-size: 12px;
            color: #0f172a;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .totalizers-table {
            width: 100%;
            border: none;
        }
        
        .totalizers-table tr {
            border-bottom: 1px solid #e2e8f0;
        }
        
        .totalizers-table tr:last-child {
            border-bottom: none;
        }
        
        .totalizer-label {
            padding: 8px 0;
            font-size: 9px;
            color: #475569;
            font-weight: 500;
        }
        
        .totalizer-type {
            color: #94a3b8;
            font-weight: normal;
            font-size: 8px;
            text-transform: lowercase;
        }
        
        .totalizer-value {
            padding: 8px 0;
            text-align: right;
            font-size: 10px;
            color: #0f172a;
            font-weight: 600;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $modelName }}</h1>
        <div class="meta">
            <strong>{{ __('ptah::ui.export_date') }}:</strong> {{ $date }} |
            <strong>{{ __('ptah::ui.export_total_records') }}:</strong> {{ count($data) }}
        </div>
    </div>

    @if(count($data) > 0)
        <table>
            <thead>
                <tr>
                    @if(empty($columns))
                        {{-- Se não foram passadas colunas, usar todas do primeiro registro --}}
                        @foreach(array_keys($data->first()->toArray()) as $column)
                            <th>{{ ucwords(str_replace('_', ' ', $column)) }}</th>
                        @endforeach
                    @else
                        {{-- Usar apenas as colunas visíveis --}}
                        @foreach($columns as $column)
                            @php
                                $label = $column['label'] ?? '';
                                // Se label vazio, usar field formatado
                                if (empty($label)) {
                                    $label = ucwords(str_replace('_', ' ', $column['field'] ?? ''));
                                }
                            @endphp
                            <th>{{ $label }}</th>
                        @endforeach
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        @if(empty($columns))
                            {{-- Se não foram passadas colunas, usar todas do registro --}}
                            @foreach(array_keys($row->toArray()) as $field)
                                <td>
                                    @php
                                        $value = $row->{$field};
                                        
                                        if ($value instanceof \DateTimeInterface) {
                                            echo $value->format('d/m/Y H:i:s');
                                        } elseif (is_bool($value)) {
                                            echo $value ? 'Sim' : 'Não';
                                        } elseif (is_string($value) && strlen($value) > 100) {
                                            echo substr($value, 0, 100) . '...';
                                        } else {
                                            echo $value ?? '-';
                                        }
                                    @endphp
                                </td>
                            @endforeach
                        @else
                            {{-- Usar apenas as colunas visíveis --}}
                            @foreach($columns as $column)
                                <td>
                                    @php
                                        $field = $column['field'] ?? '';
                                        $value = data_get($row, $field);
                                        
                                        if ($value instanceof \DateTimeInterface) {
                                            echo $value->format('d/m/Y H:i:s');
                                        } elseif (is_bool($value)) {
                                            echo $value ? 'Sim' : 'Não';
                                        } elseif (is_string($value) && strlen($value) > 100) {
                                            echo substr($value, 0, 100) . '...';
                                        } else {
                                            echo $value ?? '-';
                                        }
                                    @endphp
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totalizadores --}}
        @if(!empty($totalizers))
            <div class="totalizers">
                <h3>{{ __('ptah::ui.export_totalizers') }}</h3>
                <table class="totalizers-table">
                    @foreach($totalizers as $totalizer)
                        <tr>
                            <td class="totalizer-label">
                                {{ $totalizer['label'] }}
                                <span class="totalizer-type">({{ __('ptah::ui.export_' . $totalizer['aggregate']) }})</span>
                            </td>
                            <td class="totalizer-value">
                                {{ is_numeric($totalizer['value']) ? number_format($totalizer['value'], 2, ',', '.') : $totalizer['value'] }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @else
        <div class="no-data">
            {{ __('ptah::ui.export_no_data') }}
        </div>
    @endif
</body>
</html>
