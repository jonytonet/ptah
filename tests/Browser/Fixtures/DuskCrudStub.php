<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Ptah\Models\CrudConfig;

/**
 * Stub model usado pelos testes de navegador (ONDA IV). Reusa a tabela
 * `bulk_action_stubs` (mesma convenção dos testes Feature — ver
 * CrudKeyboardShortcutsOverlayTest, CrudDensityGlobalDefaultTest).
 *
 * `name` é obrigatório de propósito: é o campo usado pelo Fluxo 2 (validação
 * com erro → borda vermelha / aria-invalid) e pelo Fluxo 6 (pesquisa rápida).
 */
class DuskCrudStub extends Model
{
    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];

    /**
     * Cria a CrudConfig e duas linhas de dados, se ainda não existirem.
     * Chamado a cada boot da aplicação de teste (ver DuskTestCase::ensureDuskDatabaseIsReady) —
     * idempotente via firstOrCreate para não duplicar em bancos persistentes.
     */
    public static function seedFixtures(): void
    {
        CrudConfig::firstOrCreate(
            ['model' => self::class],
            [
                'route' => '',
                'config' => [
                    'crud' => self::class,
                    'cols' => [
                        ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                        [
                            'colsNomeFisico' => 'name',
                            'colsNomeLogico' => 'Nome',
                            'colsTipo' => 'text',
                            'colsGravar' => true,
                            'colsRequired' => true,
                        ],
                        [
                            'colsNomeFisico' => 'status',
                            'colsNomeLogico' => 'Status',
                            'colsTipo' => 'select',
                            'colsGravar' => true,
                            'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive'],
                        ],
                    ],
                    'permissions' => [],
                ],
            ]
        );

        self::firstOrCreate(['name' => 'Alfa Produto'], ['status' => 'active']);
        self::firstOrCreate(['name' => 'Beta Servico'], ['status' => 'active']);
    }
}
