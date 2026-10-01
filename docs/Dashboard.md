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
        ['type' => 'trend', 'label' => 'Faturamento por mês', 'model' => App\Models\Sales\Order::class,
            'aggregate' => 'sum', 'field' => 'total', 'format' => 'money', 'group' => 'month', 'days' => 365],
        ['type' => 'breakdown', 'label' => 'Vendas do mês por forma de pagamento', 'model' => App\Models\Sales\Order::class,
            'group_by' => 'paymentMethod.name', 'aggregate' => 'sum', 'field' => 'total', 'format' => 'money',
            'period' => 'month', 'limit' => 5],
        ['type' => 'latest', 'label' => 'Últimos clientes', 'model' => App\Models\Crm\Customer::class,
            'columns' => ['name', 'city'], 'limit' => 5],
    ],
];
```

| Type | Shows | Keys |
|---|---|---|
| `stat` | one number | `aggregate` (`count`, `sum`, `avg`), `field`, `period` (`today`, `week`, `month`, `year`, `all`), `format` (`number`, `money`, `percent`) |
| `trend` | bars over a window: records — or the sum/avg of `field` — per day, week or month | `days` (the window, 2–366), `group` (`day`, `week`, `month`), `aggregate`, `field`, `format` |
| `breakdown` | totals per category, horizontal bars | `group_by` (a column, or `relation.column` of a belongsTo), `aggregate`, `field`, `format`, `period`, `limit` (top N, 1–20; the rest is one "Others" bar), `labels` (`['pix' => 'PIX']`) |
| `latest` | the newest records | `columns`, `limit` (≤ 50) |

`trend` with `group`: the window becomes the nearest number of weeks or
months, starting at the beginning of one (`'group' => 'month', 'days' => 365`
is 12 bars, the current month the last). It is grouped by day in the database
— only one row per day comes back — and an average is folded as sum ÷ count,
never as an average of averages.

Common keys: `label`, `model`, `where` (`[[column, operator, value], ...]`),
`date_field` (default `created_at`), `color` (`primary`, `success`, `danger`,
`warn`), `permission`, `link`, `company_field` (default `company_id`),
`cache` (seconds, default 60).

`period` is closed at both ends (`month` is from the first to the last day of
the month), so a future date — an appointment — does not count "this month"
(until 1.43.0 it had only a start).

### Groups — widgets for other pages

`widgets` may also hold named groups. The numbered entries are the default
group, the one `/dashboard` shows; a named list is placed on any page:

```php
'widgets' => [
    ['type' => 'stat', 'label' => 'Pedidos hoje', /* ... */],   // /dashboard
    'financeiro' => [
        ['type' => 'stat', 'label' => 'A receber no mês', /* ... */],
    ],
],
```

```blade
@include('ptah::dashboard.widgets', ['group' => 'financeiro'])
```

A flat list keeps working as it always did.

What every widget respects:

- the model's **global scopes** (it is `Model::query()`);
- the **active company**, when the table has `company_field`;
- `permission`: a page object key — the widget shows only to who can `read`
  it (with the Permissions module on);
- `$hidden` attributes are never shown by `latest` nor grouped by `breakdown`.

Every column name goes through `SqlIdentifier` and every operator through an
allowlist (`=`, `!=`, `<>`, `>`, `>=`, `<`, `<=`, `like`, `in`, `not in`,
`null`, `not null`); a bad definition renders as that widget's error instead
of reaching SQL or taking the page down. The cache is per widget, company and
user — a host global scope that depends on the user cannot leak numbers.
