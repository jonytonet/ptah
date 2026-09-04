<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Livewire\AI\AiModelConfigList;

/**
 * Keeps `docs/AiAgent.md` honest about which providers exist.
 *
 * The provider table is the first thing anyone reads when wiring the module up,
 * and it is the kind of list that drifts silently: a provider added to the UI
 * and forgotten in the docs is invisible to users, and one removed from the UI
 * but left in the docs sends them to configure something that will not save.
 *
 * The same drift already happened once inside the code — the provider key map
 * in AiChatService had fallen six providers behind Prism's roster — which is why
 * this is a test and not a habit.
 */
class AiAgentDocTest extends TestCase
{
    private const DOC = __DIR__.'/../../../docs/AiAgent.md';

    /**
     * The `provider` values documented in the Providers table.
     *
     * @return list<string>
     */
    private static function documentedSlugs(): array
    {
        $doc = file_get_contents(self::DOC);

        $this_ = [];

        // Rows look like: | **xAI (Grok)** | `xai` | yes | API key from … |
        preg_match_all('/^\|\s*\*\*[^*]+\*\*\s*\|\s*`([a-z_]+)`\s*\|/m', (string) $doc, $matches);

        return array_values(array_unique($matches[1]));
    }

    #[Test]
    public function the_documented_providers_match_the_ones_the_ui_offers(): void
    {
        $documented = self::documentedSlugs();
        $offered = array_map('strval', array_keys(AiModelConfigList::PROVIDERS));

        sort($documented);
        sort($offered);

        $this->assertSame(
            $offered,
            $documented,
            "A tabela de providers em docs/AiAgent.md divergiu de AiModelConfigList::PROVIDERS.\n".
            '  so na doc: '.(implode(', ', array_diff($documented, $offered)) ?: '(nenhum)')."\n".
            '  so na UI:  '.(implode(', ', array_diff($offered, $documented)) ?: '(nenhum)')
        );
    }

    #[Test]
    public function the_provider_that_cannot_stream_is_marked_as_such(): void
    {
        // z.ai has no streaming handler, and someone comparing providers needs
        // to see that in the table rather than discover it in use. The column
        // exists for this one row; if it ever says "yes" for every provider the
        // column is lying.
        $doc = (string) file_get_contents(self::DOC);

        $this->assertMatchesRegularExpression(
            '/\|\s*\*\*z\.ai[^*]*\*\*\s*\|\s*`z`\s*\|\s*\*\*no\*\*\s*\|/',
            $doc,
            'A linha do z.ai precisa marcar streaming como **no** — e o unico provider do roster sem handler de Stream.'
        );
    }

    #[Test]
    public function the_alias_is_documented_as_requiring_an_endpoint(): void
    {
        // Without an endpoint the alias silently uses the carrier provider's own
        // default, which is the confusing failure the option exists to prevent.
        $doc = (string) file_get_contents(self::DOC);

        $this->assertStringContainsString('openai_compatible', $doc);
        $this->assertMatchesRegularExpression(
            '/API Endpoint is required|endpoint is mandatory/i',
            $doc,
            'A doc precisa dizer que o api_endpoint e obrigatorio no openai_compatible.'
        );
    }

    #[Test]
    public function every_documented_env_variable_exists_in_the_config(): void
    {
        // A documented variable the config never reads is worse than an
        // undocumented one: the reader sets it and nothing happens.
        $doc = (string) file_get_contents(self::DOC);
        $config = (string) file_get_contents(__DIR__.'/../../../config/ptah.php');

        preg_match_all('/`(PTAH_[A-Z_]+)`/', $doc, $matches);

        $missing = [];

        foreach (array_unique($matches[1]) as $variable) {
            if (! str_contains($config, $variable)) {
                $missing[] = $variable;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Variavel(is) documentada(s) em docs/AiAgent.md que config/ptah.php nao le: '.implode(', ', $missing)
        );
    }
}
