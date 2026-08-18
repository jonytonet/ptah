<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Accounts for every declaration site that leaves the layout's inline <style>
 * as its ~124 rules move onto the --ptah-* neutral tokens.
 *
 * The two golden fixtures (css-layout-baseline / css-neutral-baseline) each
 * guard one file, and that is not enough on its own: a rule that MOVES between
 * them changes both fixtures in the same commit, so regenerating the pair makes
 * a value change during the move indistinguishable from a faithful move. The
 * fixtures would be green and the colour would be different.
 *
 * This test closes that gap with a frozen origin (css-layout-origin.json, the
 * 184 sites as they stood before any migration, never regenerated) and a ledger
 * that must partition it: every origin site is either still in the layout,
 * migrated verbatim, changed deliberately, or deleted with a reason. Exactly
 * one. A site that simply disappears fails.
 */
class LayoutMigrationLedgerTest extends TestCase
{
    private const ORIGIN = __DIR__.'/../../Fixtures/css-layout-origin.json';

    private const LEDGER = __DIR__.'/../../Fixtures/css-layout-ledger.json';

    private const LAYOUT = __DIR__.'/../../Fixtures/css-layout-baseline.json';

    private const NEUTRAL = __DIR__.'/../../Fixtures/css-neutral-baseline.json';

    #[Test]
    public function every_origin_site_is_accounted_for_exactly_once(): void
    {
        $origin = array_keys(self::json(self::ORIGIN));
        $stillInLayout = array_keys(self::json(self::LAYOUT));
        $ledger = self::ledger();

        $migrated = array_keys($ledger['migrated']);
        $changed = array_keys($ledger['changed']);
        $deleted = array_keys($ledger['deleted']);

        $claimed = [...$stillInLayout, ...$migrated, ...$changed, ...$deleted];

        $unaccounted = array_values(array_diff($origin, $claimed));
        $this->assertSame(
            [],
            $unaccounted,
            "Sitios que existiam no bloco <style> e sumiram sem entrada no ledger.\n".
            "Cada um tem de ser classificado em migrated / changed / deleted:\n".
            implode("\n", $unaccounted)
        );

        $duplicated = array_keys(array_filter(
            array_count_values($claimed),
            static fn (int $n): bool => $n > 1
        ));
        $this->assertSame(
            [],
            $duplicated,
            "Sitios reivindicados por mais de uma categoria — a particao tem de ser disjunta.\n".
            "Caso tipico: a regra foi copiada para ptah-components.css e esquecida no layout,\n".
            "o que deixa duas declaracoes concorrentes para o mesmo elemento:\n".
            implode("\n", $duplicated)
        );

        $foreign = array_values(array_diff([...$migrated, ...$changed, ...$deleted], $origin));
        $this->assertSame(
            [],
            $foreign,
            "Entradas do ledger que nao existem na origem congelada (typo no seletor, ou sitio inventado):\n".
            implode("\n", $foreign)
        );
    }

    /**
     * A migrated site must render the SAME colour it rendered in the layout.
     * This is the assertion that makes "eu movi a regra" checkable: the value is
     * compared against the frozen origin, not against whatever the fixture says
     * today, so regenerating the fixtures cannot launder a change.
     */
    #[Test]
    public function migrated_sites_render_the_same_value_they_had_in_the_layout(): void
    {
        $origin = self::json(self::ORIGIN);
        $neutral = self::json(self::NEUTRAL);
        $migrated = self::ledger()['migrated'];

        if ($migrated === []) {
            $this->addToAssertionCount(1);

            return;
        }

        foreach ($migrated as $site => $recordedValue) {
            $this->assertArrayHasKey(
                $site,
                $neutral,
                sprintf('Sitio [%s] consta como migrado mas nao aparece em ptah-components.css.', $site)
            );
            $this->assertSame(
                $origin[$site],
                $recordedValue,
                sprintf('Ledger registra valor divergente da origem para [%s].', $site)
            );
            $this->assertSame(
                $origin[$site],
                $neutral[$site],
                sprintf(
                    "Sitio [%s] mudou de cor durante a migracao.\n  no layout (origem): %s\n  em ptah-components.css: %s\n".
                    'Se a mudanca e deliberada, mova a entrada para "changed" com from/to/reason.',
                    $site,
                    $origin[$site],
                    $neutral[$site]
                )
            );
        }
    }

    #[Test]
    public function deliberate_changes_declare_from_to_and_reason_and_match_reality(): void
    {
        $origin = self::json(self::ORIGIN);
        $neutral = self::json(self::NEUTRAL);
        $changed = self::ledger()['changed'];

        if ($changed === []) {
            $this->addToAssertionCount(1);

            return;
        }

        foreach ($changed as $site => $entry) {
            foreach (['from', 'to', 'reason'] as $field) {
                $this->assertArrayHasKey($field, $entry, sprintf('Entrada "changed" [%s] sem campo "%s".', $site, $field));
            }

            $this->assertSame(
                $origin[$site],
                $entry['from'],
                sprintf('Entrada "changed" [%s] declara "from" que nao e o valor de origem.', $site)
            );

            // A brand-token substitution (var(--color-primary, ...)) cannot be
            // resolved to a hex from source, so those sites legitimately carry a
            // non-hex "to" — they are covered by ContrastGuardTest instead.
            $this->assertArrayHasKey(
                $site,
                $neutral,
                sprintf('Sitio [%s] consta como alterado mas nao aparece em ptah-components.css.', $site)
            );
            $this->assertSame(
                $neutral[$site],
                $entry['to'],
                sprintf('Entrada "changed" [%s] declara "to" que nao e o que o CSS renderiza hoje.', $site)
            );

            $this->assertGreaterThan(
                20,
                strlen(trim((string) $entry['reason'])),
                sprintf('Entrada "changed" [%s] precisa de um motivo real, nao um rotulo.', $site)
            );
        }
    }

    #[Test]
    public function deleted_sites_carry_a_reason_and_are_really_gone(): void
    {
        $layout = self::json(self::LAYOUT);
        $neutral = self::json(self::NEUTRAL);
        $deleted = self::ledger()['deleted'];

        if ($deleted === []) {
            $this->addToAssertionCount(1);

            return;
        }

        foreach ($deleted as $site => $reason) {
            $this->assertGreaterThan(
                20,
                strlen(trim((string) $reason)),
                sprintf('Entrada "deleted" [%s] precisa de um motivo real.', $site)
            );
            $this->assertArrayNotHasKey($site, $layout, sprintf('Sitio [%s] consta como deletado mas segue no layout.', $site));
            $this->assertArrayNotHasKey(
                $site,
                $neutral,
                sprintf('Sitio [%s] consta como deletado mas reapareceu em ptah-components.css — isso e migracao, nao delecao.', $site)
            );
        }
    }

    /**
     * The frozen origin is the anchor for everything above, so it must not be
     * regenerable. If it ever drifts, every "migrated verbatim" claim in the
     * ledger silently loses its meaning.
     */
    #[Test]
    public function the_frozen_origin_holds_the_pre_migration_site_count(): void
    {
        $this->assertCount(
            184,
            self::json(self::ORIGIN),
            'css-layout-origin.json e um retrato imutavel do bloco <style> ANTES da migracao. '.
            'Se voce o regerou, as afirmacoes de "migrado sem mudanca" do ledger perderam a referencia — '.
            'restaure o arquivo do git em vez de reescreve-lo.'
        );
    }

    /** @return array{migrated: array<string, string>, changed: array<string, array<string, string>>, deleted: array<string, string>} */
    private static function ledger(): array
    {
        $ledger = self::json(self::LEDGER);

        foreach (['migrated', 'changed', 'deleted'] as $section) {
            if (! array_key_exists($section, $ledger)) {
                throw new RuntimeException(sprintf('css-layout-ledger.json: secao "%s" ausente.', $section));
            }
        }

        return [
            'migrated' => $ledger['migrated'],
            'changed' => $ledger['changed'],
            'deleted' => $ledger['deleted'],
        ];
    }

    /** @return array<string, mixed> */
    private static function json(string $path): array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('LayoutMigrationLedgerTest: falha ao ler '.$path);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        unset($decoded['_doc']);

        return $decoded;
    }
}
