<?php

declare(strict_types=1);

namespace Ptah\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Ptah\PtahServiceProvider;

/**
 * Classe base para todos os testes do pacote Ptah.
 *
 * Usa Orchestra Testbench para simular uma aplicação Laravel completa,
 * com banco de dados SQLite em memória e Livewire 3 registrado.
 */
abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PtahServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Banco SQLite em memória
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Módulos Ptah habilitados
        $app['config']->set('ptah.modules.company', true);
        $app['config']->set('ptah.modules.menu', false);
        $app['config']->set('ptah.modules.permissions', true);

        // Evita erros de session
        $app['config']->set('session.driver', 'array');
    }

    /**
     * Executa as migrations do Ptah e do projeto base.
     */
    protected function defineDatabaseMigrations(): void
    {
        // Migrations do Laravel base (cria tabela users, etc.)
        $this->loadLaravelMigrations();

        // Migrations do Ptah (companies, roles, etc.)
        $this->loadMigrationsFrom(__DIR__ . '/../src/Migrations');
    }
}
