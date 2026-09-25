<?php

/*
|--------------------------------------------------------------------------
| System settings (/ptah-settings, ptah_setting())
|--------------------------------------------------------------------------
|
| Declare here the settings an administrator may change without a deploy.
| The values live in the ptah_settings table (`php artisan ptah:settings:install`),
| per company when `per_company` is on, falling back to the global value and
| then to `default`. Read them anywhere with `ptah_setting('key')`.
|
| Types: text, textarea, integer, decimal, boolean, email, url, date, select
| (with `options` => [value => label]). `rules` adds Laravel validation rules.
| `secret` => true masks the value on screen (it is still stored as given —
| keep real credentials in .env, never here).
|
| Example:
|
|   'definitions' => [
|       'company_display_name' => ['label' => 'Nome exibido', 'type' => 'text', 'group' => 'Geral', 'default' => ''],
|       'invoice_due_days' => ['label' => 'Vencimento (dias)', 'type' => 'integer', 'group' => 'Financeiro', 'default' => 30, 'rules' => 'min:0|max:365'],
|       'orders_require_approval' => ['label' => 'Pedido exige aprovação', 'type' => 'boolean', 'group' => 'Vendas', 'default' => false],
|       'default_carrier' => ['label' => 'Transportadora padrão', 'type' => 'select', 'group' => 'Vendas',
|           'options' => ['correios' => 'Correios', 'jadlog' => 'Jadlog'], 'default' => 'correios'],
|   ],
*/

return [
    'per_company' => env('PTAH_SETTINGS_PER_COMPANY', true),

    'definitions' => [],
];
