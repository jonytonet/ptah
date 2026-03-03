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
        // Test migrations: creates the users table (timestamp 2014_...) so it
        // exists before Ptah migrations (e.g. add_two_factor_columns_to_users)
        // attempt to ALTER it. loadLaravelMigrations() is intentionally absent
        // because Testbench 10 ships an empty laravel/database/migrations dir.
        $this->loadMigrationsFrom(__DIR__ . '/migrations');

        // Migrations do Ptah (companies, roles, etc.)
        $this->loadMigrationsFrom(__DIR__ . '/../src/Migrations');
    }
}
