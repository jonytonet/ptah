<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guard leve para docs/CustomScreens.md (guia de telas customizadas para
 * devs de projetos consumidores, fora do BaseCrud):
 *
 *  1. O doc existe.
 *  2. Todo componente `forge-*` publicado em resources/views/components/ é
 *     citado no doc pelo nome — evita deixar um componente novo
 *     indocumentado silenciosamente.
 *  3. Todo token `--ptah-*` citado no doc realmente existe (é declarado) em
 *     resources/css/ptah-components.css — evita o doc "prometer" um token
 *     que foi removido/renomeado.
 */
class CustomScreensDocTest extends TestCase
{
    private const DOC_PATH = __DIR__.'/../../../docs/CustomScreens.md';

    private const CSS_PATH = __DIR__.'/../../../resources/css/ptah-components.css';

    private const COMPONENTS_DIR = __DIR__.'/../../../resources/views/components';

    #[Test]
    public function the_doc_file_exists(): void
    {
        $this->assertFileExists(self::DOC_PATH);
    }

    #[Test]
    public function every_published_forge_component_is_named_in_the_doc(): void
    {
        $doc = self::doc();

        $missing = [];

        foreach (self::forgeComponentNames() as $name) {
            if (! str_contains($doc, $name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "docs/CustomScreens.md nao cita o(s) componente(s) forge-* abaixo (glob de\n".
            "resources/views/components/forge-*.blade.php). Adicione pelo menos uma mencao\n".
            "(catalogo detalhado ou a lista \"Outros componentes publicados\") para nao deixar\n".
            "um componente novo indocumentado:\n  ".implode("\n  ", $missing)
        );
    }

    #[Test]
    public function every_ptah_token_cited_in_the_doc_exists_in_the_stylesheet(): void
    {
        $doc = self::doc();

        if (! preg_match_all('/--ptah-[a-zA-Z0-9-]+/', $doc, $matches)) {
            $this->fail('docs/CustomScreens.md nao cita nenhum token --ptah-* — o guard nao teria o que verificar.');
        }

        $citedTokens = array_unique($matches[0]);
        $definedTokens = self::definedCssTokens();

        $unknown = array_values(array_diff($citedTokens, $definedTokens));

        $this->assertSame(
            [],
            $unknown,
            "docs/CustomScreens.md cita token(s) --ptah-* que NAO sao declarados em\n".
            "resources/css/ptah-components.css (removidos ou renomeados desde que o doc foi\n".
            "escrito):\n  ".implode("\n  ", $unknown)
        );
    }

    /** @return list<string> nomes tipo "forge-button", sem extensao */
    private static function forgeComponentNames(): array
    {
        $files = glob(self::COMPONENTS_DIR.'/forge-*.blade.php');

        if ($files === false || $files === []) {
            throw new RuntimeException('CustomScreensDocTest: nenhum componente forge-*.blade.php encontrado — glob quebrado?');
        }

        return array_map(
            static fn (string $path): string => basename($path, '.blade.php'),
            $files
        );
    }

    /** @return list<string> tokens --ptah-* realmente declarados (com ":" na sequencia) na stylesheet */
    private static function definedCssTokens(): array
    {
        $css = self::css();

        if (! preg_match_all('/(--ptah-[a-zA-Z0-9-]+)\s*:/', $css, $matches)) {
            throw new RuntimeException('CustomScreensDocTest: nenhum token --ptah-* declarado em ptah-components.css.');
        }

        return array_values(array_unique($matches[1]));
    }

    private static function doc(): string
    {
        $content = file_get_contents(self::DOC_PATH);

        if ($content === false) {
            throw new RuntimeException('CustomScreensDocTest: falha ao ler docs/CustomScreens.md.');
        }

        return $content;
    }

    private static function css(): string
    {
        $content = file_get_contents(self::CSS_PATH);

        if ($content === false) {
            throw new RuntimeException('CustomScreensDocTest: falha ao ler resources/css/ptah-components.css.');
        }

        return $content;
    }
}
