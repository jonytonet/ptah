<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Ratchet against `/ptah-permission-guide` (the in-app manual) drifting back
 * into the falsehoods this wave corrected — same idiom as `SkillGuidanceTest`
 * (a shipped doc surface gets a guard with the same severity as code).
 *
 * FORBIDDEN terms would make copy-pasted sample code fatal-error or point at
 * a setting that does not exist:
 *  - `Ptah\Traits\HasPermission` / `requirePermission` — the trait was never
 *    in this package (`src/Traits/` has 5, none of them this).
 *  - `Ptah\Models\Page` (bare) — the class is `Ptah\Models\PtahPage`.
 *  - `PTAH_AUDIT_MAX_RECORDS` — does not exist anywhere in the repository.
 *  - `Str::slug` — `ptah_has_role()`/`hasRole()` dropped that match on
 *    purpose (see `PermissionService::roleNamesMatch()`); still teaching it
 *    would make a role name collide with a project that deliberately kept
 *    two names distinct.
 *  - `cache:clear` — invalidation is generation-based and instant; the guide
 *    used to teach a manual step that was never needed.
 *
 * REQUIRED terms are the two most recent features (qualified keys, v1.19;
 * `colsPermission`, v1.20) and the diagnostic command — all previously
 * completely absent from the guide.
 *
 * Scans the VIEW plus every `guide_*` lang key in BOTH locales, concatenated,
 * so a term is caught wherever it lives — the same reason
 * `GuidePaletteFreeLangTest` exists as a lang-aware sibling of
 * `HardcodedPaletteCeilingTest`.
 */
class PermissionGuideClaimsTest extends TestCase
{
    private const VIEW_PATH = 'resources/views/livewire/permission/permission-guide.blade.php';

    private const FORBIDDEN = [
        'Ptah\Traits\HasPermission',
        'requirePermission',
        'PTAH_AUDIT_MAX_RECORDS',
        'cache:clear',
        'Str::slug',
    ];

    /**
     * `Ptah\Models\Page` as a BARE class reference — i.e. NOT immediately
     * followed by `Object` (`PageObject`, a real class) and not itself part
     * of `PtahPage` (which never matches this pattern to begin with: the
     * literal substring `Models\Page` requires a backslash directly
     * followed by "Page", which `Models\PtahPage` does not contain). A
     * plain string check can't express "not followed by Object", hence a
     * dedicated regex instead of one more FORBIDDEN entry.
     */
    private const FORBIDDEN_PATTERN = '/Ptah\\\\Models\\\\Page(?!Object)\b/';

    private const REQUIRED = [
        '::',
        'colsPermission',
        'ptah:permission:why',
        'permissionIdentifier',
    ];

    #[Test]
    public function the_guide_never_reintroduces_a_forbidden_term(): void
    {
        $haystack = self::everything();

        foreach (self::FORBIDDEN as $term) {
            $this->assertStringNotContainsString(
                $term,
                $haystack,
                "O termo proibido \"{$term}\" apareceu de volta na tela /ptah-permission-guide (view ou chaves guide_*) — ".
                'é exatamente a falsidade que este lote corrigiu; ver o relatorio da tarefa para a redacao certa.'
            );
        }

        $this->assertSame(
            0,
            preg_match(self::FORBIDDEN_PATTERN, $haystack),
            'A tela voltou a mencionar "Ptah\Models\Page" (bare) — essa classe nao existe, e chama-se Ptah\Models\PtahPage.'
        );
    }

    #[Test]
    public function the_guide_still_teaches_every_required_term(): void
    {
        $haystack = self::everything();

        foreach (self::REQUIRED as $term) {
            $this->assertStringContainsString(
                $term,
                $haystack,
                "O termo obrigatorio \"{$term}\" nao aparece mais na tela /ptah-permission-guide (view ou chaves guide_*)."
            );
        }
    }

    private static function everything(): string
    {
        $parts = [self::viewContent()];

        foreach (['pt_BR', 'en'] as $locale) {
            foreach (self::lang($locale) as $key => $value) {
                if (str_starts_with($key, 'guide_') && is_string($value)) {
                    $parts[] = $value;
                }
            }
        }

        return implode("\n", $parts);
    }

    private static function viewContent(): string
    {
        $path = dirname(__DIR__, 3).'/'.self::VIEW_PATH;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('PermissionGuideClaimsTest: falha ao ler '.self::VIEW_PATH);
        }

        return $content;
    }

    /** @return array<string, mixed> */
    private static function lang(string $locale): array
    {
        return require dirname(__DIR__, 3)."/resources/lang/{$locale}/ui.php";
    }
}
