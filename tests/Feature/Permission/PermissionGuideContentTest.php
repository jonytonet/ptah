<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;

/**
 * Renders all 4 tabs of /ptah-permission-guide for a MASTER user, in both
 * shipped locales — the FAQ tab's bug this wave fixed (A22: hardcoded
 * Portuguese in the view while translated `guide_faq_*` keys existed and
 * were never consulted) would only ever surface under `en`, and a missing
 * `guide_*` key anywhere shows up literally as `ptah::ui.the_key` in the
 * rendered HTML — `assertDontSee('ptah::ui.')` catches that class of typo
 * for every tab at once, in every locale, without enumerating every key.
 */
class PermissionGuideContentTest extends TestCase
{
    private function mockMaster(): void
    {
        $stub = new class extends PermissionService
        {
            public function __construct() {}

            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMaster();
    }

    /** @return array<string, array{0: string}> */
    public static function localeProvider(): array
    {
        return [
            'pt_BR' => ['pt_BR'],
            'en' => ['en'],
        ];
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function every_tab_renders_without_a_missing_translation_key(string $locale): void
    {
        app()->setLocale($locale);

        foreach (['overview', 'setup', 'code', 'faq'] as $tab) {
            $html = Livewire::test(PermissionGuide::class)
                ->set('activeTab', $tab)
                ->assertOk()
                ->html();

            $this->assertStringNotContainsString(
                'ptah::ui.',
                $html,
                "Aba \"{$tab}\" ({$locale}): saida contem uma chave de traducao crua (\"ptah::ui.\"), sinal de chave faltando."
            );
        }
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function overview_tab_has_an_anchor_marker(string $locale): void
    {
        app()->setLocale($locale);

        Livewire::test(PermissionGuide::class)
            ->set('activeTab', 'overview')
            ->assertOk()
            ->assertSeeHtml('ptah-c-guide_node');
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function setup_tab_teaches_permission_identifier_and_cols_permission(string $locale): void
    {
        app()->setLocale($locale);

        Livewire::test(PermissionGuide::class)
            ->set('activeTab', 'setup')
            ->assertOk()
            ->assertSee('permissionIdentifier')
            ->assertSee('colsPermission')
            ->assertSee('ptah:permission:sync');
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function code_tab_teaches_the_qualified_key_and_the_real_contract(string $locale): void
    {
        app()->setLocale($locale);

        Livewire::test(PermissionGuide::class)
            ->set('activeTab', 'code')
            ->assertOk()
            ->assertSee('vendas::exportar')
            ->assertSee('PermissionServiceContract');
    }

    #[Test]
    #[DataProvider('localeProvider')]
    public function faq_tab_teaches_the_why_command_and_renders_in_the_active_locale(string $locale): void
    {
        app()->setLocale($locale);

        $html = Livewire::test(PermissionGuide::class)
            ->set('activeTab', 'faq')
            ->assertOk()
            ->assertSee('ptah:permission:why')
            ->html();

        $expected = $locale === 'en'
            ? __('ptah::ui.guide_faq_q1', [], 'en')
            : __('ptah::ui.guide_faq_q1', [], 'pt_BR');

        $this->assertStringContainsString($expected, $html);
    }
}
