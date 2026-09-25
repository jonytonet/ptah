# System settings

Values an administrator changes without a deploy — declared in code, edited
at `/ptah-settings`, read with `ptah_setting()`.

```bash
php artisan ptah:settings:install   # migration into database/migrations + config/ptah-settings.php
php artisan migrate
```

```php
// config/ptah-settings.php
return [
    'per_company' => true,
    'definitions' => [
        'invoice_due_days' => ['label' => 'Vencimento (dias)', 'type' => 'integer', 'group' => 'Financeiro',
            'default' => 30, 'rules' => 'min:0|max:365'],
        'orders_require_approval' => ['label' => 'Pedido exige aprovação', 'type' => 'boolean', 'group' => 'Vendas',
            'default' => false],
        'default_carrier' => ['label' => 'Transportadora padrão', 'type' => 'select', 'group' => 'Vendas',
            'options' => ['correios' => 'Correios', 'jadlog' => 'Jadlog'], 'default' => 'correios'],
    ],
];
```

```php
$days = ptah_setting('invoice_due_days');          // int
if (ptah_setting('orders_require_approval')) { … } // bool
```

- **Types:** `text`, `textarea`, `integer`, `decimal`, `boolean`, `email`,
  `url`, `date`, `select` (with `options` => `[value => label]`). Values come
  back with their type. `rules` adds Laravel validation rules; a select only
  accepts its options.
- **Resolution:** the active company's value → the global value → `default`.
  With `per_company` off there is only the global value. On the screen a
  company value shows "Use the global value" to drop it.
- **Only declared keys** exist: `ptah_setting('typo')` returns the caller's
  default, and writing an undeclared key throws — a typo never creates a
  setting nobody reads.
- **Cache:** one read per company, dropped on every write; changing a global
  value reaches every company.
- **Access:** the same rule as the menu and company screens
  (`ptah_can_manage_structure()`: master with the Permissions module,
  `PTAH_STRUCTURE_EDITOR` without it); with the Permissions module, only a
  master edits the global values.
- Not for credentials: keep secrets in `.env`.
