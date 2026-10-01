<?php

/*
|--------------------------------------------------------------------------
| Dashboard widgets (/dashboard)
|--------------------------------------------------------------------------
|
| With no widget, /dashboard shows the welcome cards it always did. Each
| widget reads one Eloquent model — its global scopes apply, and the active
| company too when the table has `company_field` (default company_id).
|
| type:
|   stat       one number: aggregate count|sum|avg of `field`, over `period`
|   trend      bars over the last `days` days: count, or sum|avg of `field`,
|              per day|week|month (`group`)
|   breakdown  totals per category: `group_by` a column or `relation.column`
|              (belongsTo), top `limit` + "others", optional `labels`
|   latest     the newest `limit` records, showing `columns`
|
| Common keys: label, model, where ([[column, operator, value], ...]),
| date_field (default created_at), period (today|week|month|year|all),
| format (number|money|percent), color (primary|success|danger|warn),
| permission (a page object key; the widget shows only to who can `read` it),
| link (a URL the widget opens), cache (seconds, default 60).
|
| Example:
|
|   'widgets' => [
|       ['type' => 'stat', 'label' => 'Pedidos hoje', 'model' => App\Models\Sales\Order::class, 'period' => 'today', 'link' => '/sales/orders'],
|       ['type' => 'stat', 'label' => 'Faturado no mês', 'model' => App\Models\Sales\Order::class,
|           'aggregate' => 'sum', 'field' => 'total', 'period' => 'month', 'format' => 'money',
|           'where' => [['status', '=', 'invoiced']], 'permission' => 'pageOrder'],
|       ['type' => 'trend', 'label' => 'Pedidos por dia', 'model' => App\Models\Sales\Order::class, 'days' => 30],
|       ['type' => 'trend', 'label' => 'Faturamento por mês', 'model' => App\Models\Sales\Order::class,
|           'aggregate' => 'sum', 'field' => 'total', 'format' => 'money', 'group' => 'month', 'days' => 365],
|       ['type' => 'breakdown', 'label' => 'Vendas por forma', 'model' => App\Models\Sales\Order::class,
|           'group_by' => 'paymentMethod.name', 'aggregate' => 'sum', 'field' => 'total', 'period' => 'month'],
|       ['type' => 'latest', 'label' => 'Últimos clientes', 'model' => App\Models\Crm\Customer::class,
|           'columns' => ['name', 'city'], 'limit' => 5],
|   ],
|
| Named groups, for other pages: 'financeiro' => [ ...widgets... ] next to
| the numbered ones, placed with
| @include('ptah::dashboard.widgets', ['group' => 'financeiro']).
*/

return [
    'widgets' => [],
];
