<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Dusk\Browser;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\Dusk\Options as DuskOptions;
use Orchestra\Testbench\Dusk\TestCase as OrchestraDuskTestCase;
use Prism\Prism\PrismServiceProvider;
use Ptah\PtahServiceProvider;
use Ptah\Tests\Browser\Fixtures\DuskCrudStub;

/**
 * Classe base dos testes de navegador (ONDA IV / Fase 2.5).
 *
 * Espelha tests/TestCase.php (mesmos providers, mesmo bootstrap de ambiente),
 * mas serve a aplicação via um servidor HTTP real (Chrome/chromedriver) em vez
 * de renderizar componentes Livewire in-process — a única forma de pegar bugs
 * que só existem no DOM renderizado (medição de layout, foco, guarda de
 * visibilidade de dialog, comportamento real de navegação do browser).
 *
 * IMPORTANTE — por que migrar/semear dentro de `$app->booted()` e não em
 * `defineDatabaseMigrations()`/`setUp()`: o servidor Dusk (ver
 * vendor/orchestra/testbench-dusk/src/server.php) atende cada requisição HTTP
 * reconstruindo a aplicação do ZERO chamando `createServingApplicationForDuskServer()`,
 * que NUNCA passa pelo ciclo de vida do PHPUnit (`setUp()`/`RefreshDatabase`) —
 * só por `getEnvironmentSetUp()` e o boot normal do container. Por isso o
 * banco (sqlite `:memory:`, igual ao resto da suíte) e os dados de fixture
 * (CrudConfig + registros do stub) são preparados aqui, de forma idempotente,
 * a cada boot — inclusive no processo do próprio servidor.
 */
abstract class DuskTestCase extends OrchestraDuskTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PrismServiceProvider::class,
            ExcelServiceProvider::class,
            DomPdfServiceProvider::class,
            PtahServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // FIXA, não aleatória por boot (diferente de tests/TestCase.php): o
        // servidor Dusk reconstrói a aplicação do ZERO em toda requisição
        // (ver comentário de classe). Livewire deriva o prefixo de URL dos
        // seus próprios assets/endpoint de sha256(app.key.'livewire-endpoint')
        // (EndpointResolver::prefix()) — uma key aleatória por boot faz a
        // requisição que carrega a página embutir um prefixo, e a requisição
        // seguinte do navegador para buscar livewire.js (outro boot, outra
        // key) cair em outro prefixo: 404, Alpine/Livewire nunca inicializam,
        // e nenhum teste de navegador funciona (medido empiricamente).
        $app['config']->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('ptah.modules.company', true);
        $app['config']->set('ptah.modules.menu', false);
        $app['config']->set('ptah.modules.permissions', true);

        // 'file', não 'array' (diferente de tests/TestCase.php): cada
        // requisição HTTP ao servidor Dusk reboota a aplicação do zero — uma
        // sessão só em memória some entre a requisição que renderiza a
        // página e a chamada Ajax subsequente do Livewire (clicar "Novo",
        // salvar…), que então falha o CSRF com "Page Expired" (medido
        // empiricamente). O arquivo de sessão no storage do skeleton
        // persiste entre os dois processos porque é o mesmo disco.
        $app['config']->set('session.driver', 'file');

        // Sidebar fixture usada pelo Fluxo 3 (Ctrl+B / grupo colapsado / item
        // ativo): um menuGroup com um filho cuja rota bate com /dusk-test/crud,
        // então visitar essa tela deixa o grupo "ativo" (ver forge-sidebar.blade.php).
        $app['config']->set('ptah.forge.sidebar_items', [
            [
                'label' => 'Inicio',
                'url' => '/dusk-test/other',
                'icon' => 'bx bx-home-alt',
                'type' => 'menuLink',
                'match' => 'dusk-test/other',
            ],
            [
                'label' => 'Catalogo',
                'icon' => 'bx bx-cube',
                'type' => 'menuGroup',
                'children' => [
                    [
                        'label' => 'Produtos',
                        'url' => '/dusk-test/crud',
                        'match' => 'dusk-test/crud',
                    ],
                ],
            ],
        ]);

        $app['view']->addLocation(__DIR__.'/Fixtures/views');

        $app->booted(function (Application $app) {
            $this->ensureDuskDatabaseIsReady($app);
        });
    }

    /**
     * Migra e semeia o banco em memória do boot atual (main process ou
     * processo servidor — ver comentário de classe). Idempotente por boot:
     * como o boot inteiro parte de um sqlite `:memory:` vazio, o guard
     * `Schema::hasTable` só evita trabalho duplicado se este callback for
     * chamado mais de uma vez no MESMO boot.
     */
    protected function ensureDuskDatabaseIsReady(Application $app): void
    {
        if (Schema::connection('testing')->hasTable('crud_configs')) {
            return;
        }

        Artisan::call('migrate', [
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/../migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        Artisan::call('migrate', [
            '--database' => 'testing',
            '--path' => realpath(__DIR__.'/../../src/Migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);

        DuskCrudStub::seedFixtures();
    }

    /**
     * Rotas de teste — servidas pelo processo Dusk (php -S), não pelo processo
     * PHPUnit principal. Ver server.php de orchestra/testbench-dusk: cada
     * request reconstrói a aplicação chamando de novo estes mesmos hooks.
     */
    protected function defineWebRoutes($router): void
    {
        // Serve resources/css/ptah-components.css cru — o layout de teste não
        // tem build Vite (cai no CDN do Tailwind), então os tokens --ptah-*
        // (densidade, tema escuro, colapso de rótulo da toolbar) só existem se
        // este arquivo for carregado explicitamente via <link>.
        $router->get('/dusk-test/ptah-components.css', function () {
            $css = file_get_contents(__DIR__.'/../../resources/css/ptah-components.css');

            return response($css, 200, ['Content-Type' => 'text/css']);
        });

        $router->get('/dusk-test/crud', function () {
            return view('dusk-crud', ['model' => DuskCrudStub::class]);
        });

        // Tela trivial para o Fluxo 6 (pesquisa + voltar): navegar para fora do
        // BaseCrud e usar o histórico do navegador para voltar.
        $router->get('/dusk-test/other', function () {
            return view('dusk-other');
        });
    }

    /**
     * Headless por padrão (necessário para rodar sem sessão gráfica /
     * ambiente de automação) — defina DUSK_HEADLESS_DISABLED=1 no ambiente
     * para ver o Chrome de verdade durante o desenvolvimento do teste.
     */
    protected function driver(): RemoteWebDriver
    {
        if (! $this->hasHeadlessDisabled()) {
            DuskOptions::withoutUI();
        }

        return parent::driver();
    }

    /**
     * Dispara um keydown GLOBAL (nível `window`) via JavaScript, em vez de
     * `Browser::keys()`.
     *
     * Por quê: `Browser::keys($selector, ...)` depende do WebDriver mover o
     * foco real do sistema operacional para o elemento antes de sintetizar as
     * teclas — no Chrome headless isso não confiavelmente resulta em um
     * `keydown` que borbulha até `window` (medido empiricamente: os 3 atalhos
     * do Fluxo 1 nunca disparavam via `->keys('', ...)`, mesmo com o seletor
     * correto). Despachar o evento programaticamente em `document.body`
     * (que ainda borbulha até `window`, onde `@keydown.window` escuta) testa
     * exatamente a mesma lógica Alpine/JS da aplicação, só que sem depender
     * do próprio WebDriver simular o teclado do SO — a classe de bug alvo
     * aqui (guarda de visibilidade de dialog, atalho comido em silêncio) está
     * inteiramente no listener, não na entrega do evento pelo driver.
     */
    protected function dispatchGlobalKeydown(Browser $browser, string $key, bool $ctrl = false, bool $meta = false): void
    {
        $browser->script(sprintf(
            "document.body.dispatchEvent(new KeyboardEvent('keydown', %s));",
            json_encode([
                'key' => $key,
                'ctrlKey' => $ctrl,
                'metaKey' => $meta,
                'bubbles' => true,
                'cancelable' => true,
            ])
        ));
    }
}
