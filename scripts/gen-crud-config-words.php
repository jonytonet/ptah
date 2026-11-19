<?php

// Run: php scripts/gen-crud-config-words.php . resources/js/core/admin/crud-config/lang.ts
// Builds resources/js/core/admin/crud-config/lang.ts from the ptah lang files:
// every `ptah::ui.*` key the CrudConfig Blade editor (and its preview partial)
// uses, en + pt_BR, tags stripped.

$root = $argv[1];
$out = $argv[2];

$blade = file_get_contents($root.'/resources/views/livewire/base-crud/crud-config.blade.php')
    .file_get_contents($root.'/resources/views/livewire/base-crud/partials/_config-form-preview.blade.php');

preg_match_all('/ptah::ui\\.([a-zA-Z0-9_]+)/', $blade, $m1);
preg_match_all("/'(cfg_[a-z0-9_]+)'/", $blade, $m2);
$keys = array_unique(array_merge($m1[1], $m2[1]));

// Dynamic: __('ptah::ui.cfg_tip_valid_'.$rule)
foreach (['email', 'url', 'integer', 'numeric', 'cpf', 'cnpj', 'phone', 'alpha', 'alphanum', 'ncm'] as $rule) {
    $keys[] = 'cfg_tip_valid_'.$rule;
}
$keys = array_values(array_filter(array_unique($keys), fn ($k) => ! str_ends_with($k, '_')));
sort($keys);

$en = require $root.'/resources/lang/en/ui.php';
$pt = require $root.'/resources/lang/pt_BR/ui.php';

$clean = function (string $s): string {
    $s = preg_replace('~</?[a-zA-Z][^>]*>~', '', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);

    return trim($s);
};

$q = fn (string $s) => "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $s)."'";

$missing = [];
$lines = ['/**', " * The words of the BaseCrud config editor, from ptah's own lang files", ' * (resources/lang/{en,pt_BR}/ui.php) — the keys the Livewire editor uses,', ' * tags stripped. Generated; keep the keys as the lang files name them.', ' */', '', 'export const langEn = {'];
foreach ($keys as $k) {
    if (! isset($en[$k]) || ! is_string($en[$k])) {
        $missing[] = $k;

        continue;
    }
    $lines[] = "    {$k}: ".$q($clean($en[$k])).',';
}
$lines[] = '};';
$lines[] = '';
$lines[] = 'export const langPtBR: Partial<Record<keyof typeof langEn, string>> = {';
foreach ($keys as $k) {
    if (! isset($en[$k]) || ! isset($pt[$k]) || ! is_string($pt[$k])) {
        continue;
    }
    $lines[] = "    {$k}: ".$q($clean($pt[$k])).',';
}
$lines[] = '};';
$lines[] = '';

file_put_contents($out, implode("\n", $lines));
fwrite(STDERR, 'keys: '.count($keys).' missing: '.implode(',', $missing)."\n");
