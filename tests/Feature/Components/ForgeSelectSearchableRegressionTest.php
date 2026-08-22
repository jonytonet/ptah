<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Proves the `searchable` prop on forge-select.blade.php stays a true opt-in:
 * a consumer that never sets it gets none of the searchable machinery.
 *
 * HISTORY: the original version of this test rendered a frozen pre-change
 * snapshot of the whole component (tests/Fixtures/forge-select-old/) side by
 * side with the live one and asserted byte-identity. That was the right
 * one-time MIGRATION proof, but a frozen copy is a maintenance time bomb as a
 * permanent test: any legitimate future change to the non-searchable markup
 * (a new ARIA attribute, a class rename) would fail it and invite a
 * mechanical "update the snapshot" — laundering real regressions. Review
 * decision (feat/cfg-dark-and-searchable-select): the byte-identity proof was
 * executed and passed at migration time; what stays is the structural
 * contract below — the searchable-only artifacts must never leak into a
 * non-searchable render.
 */
class ForgeSelectSearchableRegressionTest extends TestCase
{
    /**
     * Every artifact that exists ONLY for the searchable mode. If any of these
     * appears in a non-searchable render, the opt-in leaked.
     *
     * @return array<string, array{0: string}>
     */
    public static function searchableOnlyArtifactProvider(): array
    {
        return [
            'filter input ref' => ['filterInput'],
            'filter state' => ['onFilterInput'],
            'filter escape handler' => ['onFilterEscape'],
            'filter matcher' => ['matchesFilter'],
            'diacritics normalizer' => ['normalizeText'],
            'filter css class' => ['ptah-select-filter'],
            'empty-state css class' => ['ptah-select-empty'],
            // A chave i18n do aria-label e coberta por ForgeSelectSearchableLangParityTest;
            // aqui o artefato estrutural equivalente e o proprio x-ref do input.
            'filter input x-ref attribute' => ['x-ref="filterInput"'],
        ];
    }

    private function renderPlainConsumer(): string
    {
        return (string) $this->blade(
            '<x-forge-select label="Status" wire:model="status" :options="[[\'value\' => \'a\', \'label\' => \'A\'], [\'value\' => \'b\', \'label\' => \'B\']]" />'
        );
    }

    #[Test]
    #[DataProvider('searchableOnlyArtifactProvider')]
    public function a_non_searchable_consumer_carries_no_searchable_artifact(string $artifact): void
    {
        $this->assertStringNotContainsString($artifact, $this->renderPlainConsumer());
    }

    #[Test]
    public function a_searchable_consumer_does_carry_the_filter_machinery(): void
    {
        // Sanity check for the provider above: the artifacts are real strings
        // the searchable mode emits — otherwise the "absence" assertions could
        // pass forever on typo'd needles.
        $html = (string) $this->blade(
            '<x-forge-select searchable label="Status" wire:model="status" :options="[[\'value\' => \'a\', \'label\' => \'A\']]" />'
        );

        foreach (self::searchableOnlyArtifactProvider() as [$artifact]) {
            $this->assertStringContainsString($artifact, $html);
        }
    }
}
