<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A package view may not depend on a colour the HOST's theme has to declare.
 *
 * The AI config panel's explanation was reported as "no contrast in dark". It
 * was not a contrast problem: it had no colour at all. The panel painted its
 * header, intro and footer with `dark:text-primary-300` /
 * `dark:text-primary-200` and its border with `dark:border-primary-400` —
 * numeric shades of the brand colour. The package's own `forge.css` declares
 * that scale, but the `app.css` a host gets from `ptah:install` declares only
 * `--color-primary`, `--color-primary-light` and `--color-primary-dark`.
 *
 * So in a host build those utilities generate nothing — grep of the compiled
 * bundle: zero occurrences — and the text fell back to inheriting its
 * ancestor's colour, which on a dark panel is dark ink. `forge-alert`'s
 * `primary` variant (and therefore `info`, which maps to it) had the identical
 * defect; the other three variants had already been migrated and this one was
 * missed.
 *
 * This is the second instance of the same shape, after the error page calling
 * `@vite` on an entry a host need not have. Hence a guard rather than a fix:
 * the failure mode is invisible from inside the package, where the scale does
 * exist.
 *
 * Surface colours come from `--ptah-*` tokens in ptah-components.css, which
 * ships with the package. Accent BASE names (`text-primary`, `bg-danger-light`)
 * are fine — those three per family are exactly what the install stub declares.
 */
class HostThemeScaleDependencyTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../resources/views';

    /** The three names per family that a host's app.css actually declares. */
    private const HOST_DECLARES = ['', '-light', '-dark'];

    private const FAMILIES = 'primary|success|danger|warn|info';

    /**
     * @return list<string>
     */
    private static function bladeFiles(): array
    {
        $files = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::VIEWS, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    #[Test]
    public function no_view_uses_a_numeric_shade_of_a_brand_colour(): void
    {
        $offenders = [];

        foreach (self::bladeFiles() as $path) {
            $source = file_get_contents($path);

            if ($source === false) {
                throw new RuntimeException("HostThemeScaleDependencyTest: falha ao ler {$path}");
            }

            // Comments first: this suite has tripped on its own prose before,
            // and the note explaining this rule names the offending classes.
            $source = (string) preg_replace('#\{\{--.*?--\}\}#s', '', $source);
            $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

            preg_match_all(
                '/\b(?:[a-z-]+:)?(?:text|bg|border|ring|from|via|to|divide|outline|shadow|accent|caret|decoration|fill|stroke)-(?:'
                    .self::FAMILIES.')-\d{2,3}\b/',
                $source,
                $matches
            );

            if ($matches[0] !== []) {
                $rel = str_replace(self::VIEWS.DIRECTORY_SEPARATOR, '', $path);
                $offenders[str_replace('\\', '/', $rel)] = array_values(array_unique($matches[0]));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'View(s) dependendo de um tom numerico da cor de marca, que o app.css do HOST nao declara '.
            '(so '.implode(', ', array_map(fn (string $s): string => '`--color-<familia>'.$s.'`', self::HOST_DECLARES)).").\n".
            'No build do host essas classes nao geram regra nenhuma e o texto herda a cor do ancestral — '.
            "num painel escuro, tinta escura sobre fundo escuro.\n".
            "Use uma classe .ptah-c-* apoiada em var(--ptah-*), que viaja com o pacote:\n".
            implode("\n", array_map(
                static fn (string $view, array $hits): string => sprintf('  %s: %s', $view, implode(', ', $hits)),
                array_keys($offenders),
                $offenders
            ))
        );
    }

    #[Test]
    public function the_base_accent_names_are_still_allowed(): void
    {
        // The counterpart: this guard must not push people off the three names
        // the host DOES declare, which the whole package uses for accents.
        $alert = file_get_contents(self::VIEWS.'/components/forge-alert.blade.php');

        $this->assertIsString($alert);
        $this->assertStringContainsString('bg-primary-light', $alert);
        $this->assertStringContainsString('text-primary', $alert);
    }

    #[Test]
    public function the_alert_primary_variant_paints_its_ink_through_a_token_class(): void
    {
        // The specific regression. `primary` is also where `info` maps, so this
        // covered four alerts in the package plus every host's own.
        $alert = file_get_contents(self::VIEWS.'/components/forge-alert.blade.php');

        $this->assertIsString($alert);
        $this->assertMatchesRegularExpression(
            "/'primary' => \[[^\]]*'title' => 'ptah-c-alert_title'/",
            $alert,
            'A variante primary precisa pintar o titulo por classe tokenizada, como as outras tres.'
        );
        $this->assertMatchesRegularExpression(
            "/'primary' => \[[^\]]*'text' => 'ptah-c-alert_text'/",
            $alert
        );
    }

    #[Test]
    public function the_alert_primary_ink_is_defined_in_both_scopes(): void
    {
        // A class that no rule paints would be the same bug with extra steps.
        $css = file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');

        $this->assertIsString($css);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.ptah-alert-primary \.ptah-c-alert_title,\s*\n\s*\.ptah-alert-primary \.ptah-c-alert_text\s*\{[^}]*color:/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.ptah-dark \.ptah-alert-primary \.ptah-c-alert_text\s*\{[^}]*color:/',
            $css
        );
    }
}
