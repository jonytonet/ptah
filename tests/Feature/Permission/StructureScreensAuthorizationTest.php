<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Company\CompanyList;
use Ptah\Livewire\Menu\MenuList;
use Ptah\Models\Menu;
use Ptah\Support\SafeUrl;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

/**
 * The menu and the companies were administered by anyone who could log in.
 *
 * `/ptah-menu` and `/ptah-companies` were behind `['web', 'auth']`, and the
 * components had no check of their own. Any authenticated user could create,
 * edit and delete companies — in a multi-tenant host — and menu items. Compare
 * the ACL screens, behind `ptah.master` and re-checked on every request by
 * `RequiresMasterAccess`, or the AI model screen with `authorizeAiConfig()`.
 *
 * The menu half was worse, because the menu item's URL was validated only as
 * `string|max:2048` and rendered as `href="{{ $itemUrl }}"`. Escaping does not
 * neutralise a scheme, so an ordinary user could store `javascript:…` in a menu
 * item and wait for a master to click it: stored XSS in the sidebar every user
 * sees, running in whoever's session clicked.
 *
 * The gate is checked in `boot()`, which Livewire runs on the initial mount AND
 * on every later action, so it covers save and delete, not only the page.
 */
class StructureScreensAuthorizationTest extends TestCase
{
    use ActsAsPtahUser;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function screenProvider(): array
    {
        return [
            'menu' => [MenuList::class],
            'companies' => [CompanyList::class],
        ];
    }

    // ── Who gets in ────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('screenProvider')]
    public function an_ordinary_user_is_turned_away(string $screen): void
    {
        $this->actAsMaster(false);

        // `Livewire::test()` registra o abort(403) como status da resposta, em
        // vez de propagar a HttpException — a primeira versao deste arquivo
        // esperava a excecao e falhou por isso, com o gate funcionando.
        Livewire::test($screen)->assertForbidden();
    }

    #[Test]
    #[DataProvider('screenProvider')]
    public function a_master_gets_in(string $screen): void
    {
        $this->actAsMaster();

        Livewire::test($screen)->assertOk();
    }

    #[Test]
    #[DataProvider('screenProvider')]
    public function an_rbac_grant_that_is_not_master_is_not_enough(string $screen): void
    {
        // Master e a regra com o modulo ativo, como nas telas de ACL. Um `can`
        // generico em qualquer objeto nao administra a estrutura.
        $this->actAsUserWhoCan(true);

        Livewire::test($screen)->assertForbidden();
    }

    #[Test]
    public function the_gate_is_rechecked_on_every_action_not_only_on_mount(): void
    {
        // O motivo de o check estar no boot(): um master rebaixado no meio da
        // sessao, ou um update forjado contra um snapshot ja montado, nao pode
        // seguir chamando save/delete.
        $this->actAsMaster();

        // O formulario e preenchido ANTES de revogar, para que o `save` seja a
        // unica requisicao depois. Cada `set()` e uma requisicao propria: com
        // tres encadeadas, o gate barra a primeira com 403, o snapshot deixa de
        // ser valido e as seguintes voltam 404 — e o assert olharia a ultima.
        $component = Livewire::test(MenuList::class)
            ->set('text', 'Invasor')
            ->set('type', 'menuLink')
            ->set('target', '_self');

        $this->actAsMaster(false);

        $component->call('save')->assertForbidden();

        $this->assertDatabaseMissing('menus', ['text' => 'Invasor']);
    }

    // ── With the permissions module off ────────────────────────────────────

    #[Test]
    #[DataProvider('screenProvider')]
    public function without_rbac_the_screens_are_denied_by_default(string $screen): void
    {
        // Sem RBAC o pacote nao distingue administrador de qualquer outro
        // usuario logado — mesmo desenho do editor de config (PTAH_CONFIG_EDITOR).
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.structure_editor', false);

        Livewire::test($screen)->assertForbidden();
    }

    #[Test]
    #[DataProvider('screenProvider')]
    public function without_rbac_the_host_can_opt_back_in(string $screen): void
    {
        config()->set('ptah.modules.permissions', false);
        config()->set('ptah.structure_editor', true);

        Livewire::test($screen)->assertOk();
    }

    // ── The stored XSS in the sidebar ──────────────────────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(document.cookie)'],
            'uppercase' => ['JAVASCRIPT:alert(1)'],
            // O que a regex antiga deixava passar: o parser WHATWG remove TAB e
            // quebra de linha de QUALQUER posicao, e controles C0 das pontas.
            'tab inside the scheme' => ["java\tscript:alert(1)"],
            'newline inside the scheme' => ["java\nscript:alert(1)"],
            'leading control character' => ["\x01javascript:alert(1)"],
            'leading space' => ['   javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeUrlProvider')]
    public function a_menu_item_cannot_be_saved_with_an_executable_url(string $url): void
    {
        $this->actAsMaster();

        Livewire::test(MenuList::class)
            ->set('text', 'Link')
            ->set('type', 'menuLink')
            ->set('target', '_self')
            ->set('url', $url)
            ->call('save')
            ->assertHasErrors('url');

        $this->assertDatabaseMissing('menus', ['text' => 'Link']);
    }

    #[Test]
    public function a_malicious_url_already_stored_is_neutralised_in_the_sidebar(): void
    {
        // A validacao na entrada nao alcanca o que foi gravado antes da 1.34.8,
        // por isso a sidebar sanitiza na SAIDA.
        //
        // Os itens vao direto na prop `items`, e ha uma ancora: a primeira
        // versao deste teste gravava o item no banco com o modulo de menu
        // DESLIGADO (o TestCase o desliga), entao a sidebar nem lia o item — e
        // o teste passava inclusive contra o codigo publicado, que nao
        // sanitizava nada. Passar a vazio e o modo de falha que ele existe para
        // pegar.
        $html = $this->blade('<x-forge-sidebar :items="$items" />', [
            'items' => [[
                'text' => 'Relatorio malicioso',
                'url' => "java	script:fetch('/x')",
                'icon' => 'bx bx-circle',
                'type' => 'menuLink',
                'children' => [],
            ]],
        ])->__toString();

        $this->assertStringContainsString('Relatorio malicioso', $html, 'O item nem foi renderizado — o teste passaria a vazio.');
        $this->assertStringNotContainsString('script:fetch', $html, 'O href executavel chegou a sidebar.');
    }

    // ── The normaliser on its own ──────────────────────────────────────────

    #[Test]
    #[DataProvider('unsafeUrlProvider')]
    public function the_normaliser_refuses_every_executable_form(string $url): void
    {
        $this->assertFalse(SafeUrl::isSafe($url), 'Aceitou: '.json_encode($url));
        $this->assertSame('#', SafeUrl::sanitize($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function safeUrlProvider(): array
    {
        return [
            'https' => ['https://exemplo.com/pedidos'],
            'http' => ['http://exemplo.com'],
            'absolute path' => ['/admin/pedidos'],
            'relative path' => ['pedidos/novo'],
            'fragment' => ['#aba'],
            'query' => ['?f[status]=aberto'],
            'protocol relative' => ['//cdn.exemplo.com/app.js'],
            'mailto' => ['mailto:suporte@exemplo.com'],
            'tel' => ['tel:+5511999999999'],
            // Um `:` depois de `/` e parte do caminho, nao um esquema.
            'colon in the path' => ['/relatorios/2026:09'],
            // Para o navegador, espaco antes do `:` quebra o esquema: e relativa.
            'space before the colon' => ['javascript :alert(1)'],
        ];
    }

    #[Test]
    #[DataProvider('safeUrlProvider')]
    public function the_normaliser_keeps_every_legitimate_form(string $url): void
    {
        // A contrapartida: um normalizador que recusasse demais quebraria
        // menus e links legitimos de todo host.
        $this->assertTrue(SafeUrl::isSafe($url), 'Recusou: '.$url);
        $this->assertSame($url, SafeUrl::sanitize($url), 'Deve devolver a URL original, nao a normalizada.');
    }
}
