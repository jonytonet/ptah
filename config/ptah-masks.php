<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ptah — máscaras de campo
|--------------------------------------------------------------------------
|
| As máscaras que a SUA aplicação usa, referenciadas por nome no crud_config:
|
|     { "colsNomeFisico": "cpf", "colsTipo": "text", "colsMask": "cpf" }
|
| O ptah embute só duas, `digits` e `decimal`, porque nenhuma das duas é lei de
| ninguém. Documento, CEP, telefone e placa mudam por país e por legislação — o
| CNPJ brasileiro virou alfanumérico em 2026 — e uma release de pacote não é o
| lugar onde essa mudança deve acontecer. Aqui é.
|
| Cada máscara tem:
|
|   pattern      Um padrão de exibição, ou uma lista deles. Tokens: `0` dígito,
|                `A` letra, `*` letra ou dígito. Qualquer outro caractere é
|                literal, inserido pela máscara. Com vários padrões, o menor que
|                ainda acomoda o que foi digitado é o usado — que é como um
|                telefone com 8 e 9 dígitos fica utilizável.
|
|   store        O que chega ao banco: 'digits', 'alnum', 'upper', 'lower',
|                'trim', 'decimal' ou 'raw'. Rodado no SERVIDOR, no save.
|
|   placeholder  Opcional. Por padrão, o primeiro padrão.
|
|   inputmode    Opcional. Por padrão 'numeric' quando o padrão não tem letra,
|                senão 'text' — forçar teclado numérico num campo que aceita
|                letra deixa o campo impossível de preencher no celular.
|
| Uma closure em `store` só funciona via `PtahMask::define()`, não aqui: o
| `php artisan config:cache` serializa este arquivo, e closure não serializa.
|
| Descomente o que usar. Nada aqui está ativo por padrão.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | presets
    |----------------------------------------------------------------------
    |
    | Conjuntos prontos que vêm no pacote como DADO, ativados por você.
    | Disponível: 'br' — cpf, cnpj (alfanumérico de 2026), documento (aceita os
    | dois no mesmo campo), cep, telefone, placa, pis, inscricao_estadual.
    |
    |     'presets' => ['br'],
    |
    | NÃO é ligado pelo locale, e isso é decisão, não esquecimento. Locale é
    | idioma de INTERFACE, não jurisdição: uma empresa brasileira com o ERP em
    | inglês continua precisando de CPF, e uma portuguesa em pt-BR não tem CPF,
    | tem NIF. Locale também muda por requisição — quem trocasse o idioma
    | perderia a máscara no meio da sessão e um documento já gravado pararia de
    | ser formatado na tela de edição.
    |
    | Se você QUISER que o idioma decida, o critério é seu, no seu provider:
    |
    |     if (app()->getLocale() === 'pt_BR') {
    |         \Ptah\Support\PtahMask::preset('br');
    |     }
    |
    | Um preset é dado de melhor esforço sobre lei de outra pessoa. O que você
    | definir abaixo GANHA do preset, então quando um formato mudar antes da
    | próxima release do ptah, sobrescreva só a máscara afetada.
    |
    */
    'presets' => [],

    // ── Suas máscaras ────────────────────────────────────────────────────
    //
    // O que estiver aqui ganha do preset e das embutidas.

    // 'cpf' => [
    //     'pattern' => '000.000.000-00',
    //     'store' => 'digits',
    // ],

    // ── Outros países ────────────────────────────────────────────────────

    // 'ssn' => [
    //     'pattern' => '000-00-0000',
    //     'store' => 'digits',
    // ],

    // 'zip_us' => [
    //     'pattern' => ['00000', '00000-0000'],
    //     'store' => 'digits',
    // ],

    // 'iban_pt' => [
    //     'pattern' => 'AA00 0000 0000 0000 0000 0000 0',
    //     'store' => 'alnum',
    // ],

];
