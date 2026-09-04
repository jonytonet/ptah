<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Permission;

use Composer\InstalledVersions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Permission\PermissionGuide;
use Ptah\Services\Permission\PermissionService;
use Ptah\Support\PtahVersion;
use Ptah\Tests\TestCase;

/**
 * The installed version, shown discreetly at the foot of the ACL guide.
 *
 * The value comes from Composer's lock data rather than a constant in the
 * package, because a constant is a second source of truth that goes stale
 * exactly when it matters — the commit right after a release. These tests care
 * about two things: that the number on screen is the one Composer reports, and
 * that a missing or unreadable version degrades to a label rather than to an
 * exception. A version footer is decoration; it must never be what breaks a
 * page.
 */
class GuideVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PtahVersion::flush();

        // O guia exige MASTER (RequiresMasterAccess::assertMasterAccess), senao
        // render() aborta e o HTML volta vazio.
        $this->app->instance(PermissionService::class, new class extends PermissionService
        {
            public function __construct() {}

            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        });
    }

    protected function tearDown(): void
    {
        PtahVersion::flush();

        parent::tearDown();
    }

    #[Test]
    public function the_version_matches_what_composer_reports(): void
    {
        // Inside this repo ptah is the root package, so the answer is a branch
        // name (`dev-main`); in a host it is the tag (`1.31.1`). Both are valid
        // answers to "which ptah is this?", so the assertion compares against
        // the source rather than pinning a literal.
        $expected = ltrim((string) InstalledVersions::getPrettyVersion(PtahVersion::PACKAGE), 'v');

        $this->assertNotSame('', $expected, 'Composer nao soube dizer a versao — o teste perderia sentido.');
        $this->assertSame($expected, PtahVersion::current());
    }

    #[Test]
    public function the_leading_v_of_a_tag_is_trimmed(): void
    {
        // The label already reads "ptah v…", so a tag written `v1.31.1` would
        // otherwise render as "ptah vv1.31.1".
        $this->assertStringStartsNotWith('v', PtahVersion::current());
    }

    #[Test]
    public function the_value_is_resolved_once(): void
    {
        $first = PtahVersion::current();

        $this->assertSame($first, PtahVersion::current());
    }

    #[Test]
    public function the_guide_renders_the_version_on_every_tab(): void
    {
        // It sits outside <x-forge-tabs>, so switching tabs must not hide it.
        $component = Livewire::test(PermissionGuide::class);

        foreach (['overview', 'setup', 'code', 'faq'] as $tab) {
            $html = $component->set('activeTab', $tab)->html();

            $this->assertStringContainsString(
                'ptah v'.PtahVersion::current(),
                $html,
                "A versao desapareceu na aba '{$tab}'."
            );
        }
    }

    #[Test]
    public function the_version_uses_a_tokenised_class_rather_than_a_brand_shade(): void
    {
        // Same trap as the AI config panel: a numeric shade of a brand family
        // generates no rule in a host build. HostThemeScaleDependencyTest
        // forbids it across every view; this pins that the replacement class is
        // actually painted, in both scopes.
        $css = (string) file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $this->assertMatchesRegularExpression('/\.ptah-c-ver\s*\{[^}]*color:/', $css);
        $this->assertMatchesRegularExpression('/\.ptah-dark \.ptah-c-ver\s*\{[^}]*color:/', $css);

        $view = (string) file_get_contents(
            __DIR__.'/../../../resources/views/livewire/permission/permission-guide.blade.php'
        );

        $this->assertStringContainsString('ptah-c-ver', $view);
    }

    #[Test]
    public function an_unresolvable_version_degrades_to_a_label(): void
    {
        // Real cases, not defensive noise: Composer 1 has no InstalledVersions,
        // and a package required by path can be absent from the lock data. The
        // class cannot be unloaded mid-suite, so the reachable way in is a
        // package name Composer does not know — the same resolve() path.
        $this->assertFalse(InstalledVersions::isInstalled('jonytonet/does-not-exist'));

        $this->assertSame('unknown', PtahVersion::for('jonytonet/does-not-exist'));
    }
}
