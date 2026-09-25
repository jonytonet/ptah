# Dashboard widgets

`/dashboard` shows widgets declared in `config/ptah-dashboard.php`
(`php artisan vendor:publish --tag=ptah-config` publishes it). With no widget,
it keeps the welcome cards it always had. The same widgets can be placed on
any page with `@include('ptah::dashboard.widgets')`.

```php
// config/ptah-dashboard.php
return [
    'widgets' => [
        ['type' => 'stat', 'label' => 'Pedidos hoje', 'model' => App\Models\Sales\Order::class,
            'period' => 'today', 'link' => '/sales/orders'],
        ['type' => 'stat', 'label' => 'Faturado no mês', 'model' => App\Models\Sales\Order::class,
            'aggregate' => 'sum', 'field' => 'total', 'period' => 'month', 'format' => 'money',
            'where' => [['status', '=', 'invoiced']], 'permission' => 'pageOrder'],
        ['type' => 'trend', 'label' => 'Pedidos por dia', 'model' => App\Models\Sales\Order::class, 'days' => 30],
        ['type' => 'latest', 'label' => 'Últimos clientes', 'model' => App\Models\Crm\Customer::class,
            'columns' => ['name', 'city'], 'limit' => 5],
    ],
];
```

| Type | Shows | Keys |
|---|---|---|
| `stat` | one number | `aggregate` (`count`, `sum`, `avg`), `field`, `period` (`today`, `week`, `month`, `year`, `all`), `format` (`number`, `money`, `percent`) |
| `trend` | records per day, bar chart | `days` (2–366) |
| `latest` | the newest records | `columns`, `limit` (≤ 50) |

Common keys: `label`, `model`, `where` (`[[column, operator, value], ...]`),
`date_field` (default `created_at`), `color` (`primary`, `success`, `danger`,
`warn`), `permission`, `link`, `company_field` (default `company_id`),
`cache` (seconds, default 60).

What every widget respects:

- the model's **global scopes** (it is `Model::query()`);
- the **active company**, when the table has `company_field`;
- `permission`: a page object key — the widget shows only to who can `read`
  it (with the Permissions module on);
- `$hidden` attributes are never shown by `latest`.

Every column name goes through `SqlIdentifier` and every operator through an
allowlist (`=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `like`, `in`, `not in`,
`null`, `not null`); a bad definition renders as that widget's error instead
of reaching SQL or taking the page down. The cache is per widget, company and
user — a host global scope that depends on the user cannot leak numbers.
