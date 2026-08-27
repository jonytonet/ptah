<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the shipped agent skill against re-teaching the theme-breaking
 * pattern, and pins the agent-routing docs to what they promise.
 *
 * Why this exists: the skill's "Design Tokens" section taught fixed-hex
 * Tailwind classes (`bg-light` = #f8fafc, `bg-dark` = #1e293b) for years after
 * the token system shipped. Agents in host projects load the skill FIRST and
 * often exclusively — so they kept producing screens that stay white when the
 * user switches the light tone to "papel". Reported from a real host by the
 * package author himself. The doc that had it right (CustomScreens.md) was
 * never contradicted; it was simply never read, because the skill answered
 * first. A skill is documentation with a priority lane, so it gets a guard
 * with the same severity as code.
 */
class SkillGuidanceTest extends TestCase
{
    private static function read(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/'.$relative;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('SkillGuidanceTest: falha ao ler '.$relative);
        }

        return $content;
    }

    private static function skill(): string
    {
        return self::read('resources/boost/skills/ptah-development/SKILL.md');
    }

    #[Test]
    public function the_skill_never_reintroduces_the_fixed_hex_token_table(): void
    {
        // The exact poison rows from the pre-1.26 table. Deliberately precise
        // strings, NOT a broad "no bg-light anywhere" scan: the current skill
        // legitimately names those classes inside its forbidden-patterns list,
        // and a guard that trips on its own teaching prose is this repo's most
        // recurrent false failure.
        foreach (['| `light` | `#f8fafc` |', '| `dark` | `#1e293b` |'] as $poison) {
            $this->assertStringNotContainsString(
                $poison,
                self::skill(),
                'A tabela de tokens com hex fixo voltou para a skill — foi ela que ensinou agentes a produzir telas que ficam brancas no tom "papel".'
            );
        }
    }

    #[Test]
    public function the_skill_teaches_tokens_and_points_at_the_full_contract(): void
    {
        $skill = self::skill();

        $this->assertStringContainsString('var(--ptah-', $skill, 'A skill precisa ensinar os tokens --ptah-*.');
        $this->assertStringContainsString('CustomScreens.md', $skill, 'A skill precisa apontar para o contrato completo de tokens.');
        $this->assertStringContainsString('papel', $skill, 'A skill precisa nomear o caso de falha real (tom papel) — é o que faz um agente levar a regra a sério.');
    }

    #[Test]
    public function the_skill_opens_with_the_configure_before_you_code_map(): void
    {
        $skill = self::skill();

        $heading = '## Decision map — configure before you code';
        $this->assertStringContainsString($heading, $skill);

        // The map must come BEFORE the architecture deep-dive: its whole point
        // is to stop an agent from reading (and then hand-building) the layered
        // stack when a config row would have sufficed.
        $this->assertLessThan(
            strpos($skill, '## SOLID Architecture'),
            strpos($skill, $heading),
            'O mapa de decisao precisa vir ANTES da secao de arquitetura — ele existe para poupar a leitura dela.'
        );
    }

    #[Test]
    public function the_agents_router_exists_and_names_the_two_rules(): void
    {
        $agents = self::read('AGENTS.md');

        $this->assertStringContainsString('ptah:config', $agents);
        $this->assertStringContainsString('CustomScreens.md', $agents);
        $this->assertStringContainsString('var(--ptah-', $agents);

        // Every doc the router promises must exist — a router that 404s is
        // worse than none.
        preg_match_all('/`(docs\/[A-Za-z-]+\.md|resources\/boost\/skills\/[a-z-]+\/SKILL\.md)`/', $agents, $m);
        $this->assertNotEmpty($m[1], 'AGENTS.md: nenhuma referencia de arquivo encontrada — o parser quebrou?');

        foreach (array_unique($m[1]) as $ref) {
            $this->assertFileExists(
                dirname(__DIR__, 3).'/'.$ref,
                "AGENTS.md aponta para {$ref}, que nao existe."
            );
        }
    }
}
