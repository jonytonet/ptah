<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Turns a physical column name into a human label — the single derivation shared
 * by `ptah:forge` (CrudConfigGenerator) and `ptah:config` (ColumnParser), which
 * used to disagree (the generator stripped `_id`, the parser produced
 * "Category Id"). It also applies a small pt-BR dictionary so common ERP fields
 * come out accented ("usuario" → "Usuário") instead of raw-titleised ASCII.
 *
 * Extend/override the dictionary via config('ptah.crud.label_dictionary').
 */
final class LabelHumanizer
{
    public static function make(string $field): string
    {
        // Drop a trailing FK marker, normalise case.
        $normalized = strtolower(trim((string) preg_replace('/_id$/', '', $field)));

        if ($normalized === '') {
            return Str::title(str_replace('_', ' ', $field));
        }

        $dictionary = self::dictionary();

        if (isset($dictionary[$normalized])) {
            return $dictionary[$normalized];
        }

        // UTF-8-safe title case of the de-underscored form.
        return Str::title(str_replace('_', ' ', $normalized));
    }

    /**
     * @return array<string, string> normalized field name → label
     */
    private static function dictionary(): array
    {
        $builtin = [
            'nome' => 'Nome',
            'usuario' => 'Usuário',
            'usuarios' => 'Usuários',
            'email' => 'E-mail',
            'e_mail' => 'E-mail',
            'senha' => 'Senha',
            'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'rg' => 'RG',
            'telefone' => 'Telefone',
            'celular' => 'Celular',
            'endereco' => 'Endereço',
            'cep' => 'CEP',
            'cidade' => 'Cidade',
            'estado' => 'Estado',
            'pais' => 'País',
            'bairro' => 'Bairro',
            'numero' => 'Número',
            'complemento' => 'Complemento',
            'descricao' => 'Descrição',
            'observacao' => 'Observação',
            'observacoes' => 'Observações',
            'situacao' => 'Situação',
            'status' => 'Status',
            'codigo' => 'Código',
            'preco' => 'Preço',
            'valor' => 'Valor',
            'estoque' => 'Estoque',
            'quantidade' => 'Quantidade',
            'data_nascimento' => 'Data de Nascimento',
            'razao_social' => 'Razão Social',
            'inscricao_estadual' => 'Inscrição Estadual',
            'ativo' => 'Ativo',
            'categoria' => 'Categoria',
        ];

        $override = [];
        foreach ((array) config('ptah.crud.label_dictionary', []) as $key => $value) {
            $override[strtolower((string) $key)] = (string) $value;
        }

        return array_merge($builtin, $override);
    }
}
