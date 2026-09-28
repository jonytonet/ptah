<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Wizards\FilterWizard;
use Ptah\Support\FilterRule;
use Ptah\Tests\TestCase;

/** Answers the wizard's questions from a script, in order. */
class ScriptedCommand extends Command
{
    /** @param list<mixed> $answers */
    public function __construct(private array $answers)
    {
        parent::__construct();
    }

    private function next(): mixed
    {
        return array_shift($this->answers);
    }

    public function ask($question, $default = null)
    {
        return $this->next() ?? $default;
    }

    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = false)
    {
        return $this->next() ?? $default;
    }

    public function confirm($question, $default = false)
    {
        $answer = $this->next();

        return $answer === null ? $default : (bool) $answer;
    }

    public function info($string, $verbosity = null) {}

    public function warn($string, $verbosity = null) {}

    public function newLine($count = 1)
    {
        return $this;
    }

    public function table($headers, $rows, $tableStyle = 'default', array $columnStyles = []) {}
}

/**
 * The filter wizard wrote `colsFilterOptions`, `colsFilterWhereHas`,
 * `colsFilterRelationField` and `colsFilterAggregate`, which FilterRule dropped
 * on normalisation and nothing read — a select filter built interactively had
 * no options and a relation filter had no relation (found by
 * ConfigKeyReachabilityTest, fixed in 1.41.4).
 */
class FilterWizardVocabularyTest extends TestCase
{
    #[Test]
    public function a_select_filter_keeps_its_options_through_normalisation(): void
    {
        $filter = (new FilterWizard(new ScriptedCommand([
            'status', 'Situação', 'select', '=',
            true, 'open', 'Aberto',     // opção 1: valor, rótulo
            true, 'done', 'Concluído',  // opção 2
            false,                      // não adicionar mais
            false,                      // sem whereHas
            true,                       // salvar
        ])))->run();

        $normalized = FilterRule::normalize($filter);

        // O painel le `colsSelect` como rotulo => valor.
        $this->assertSame(['Aberto' => 'open', 'Concluído' => 'done'], $normalized['colsSelect'] ?? null);
        $this->assertSame('status', $normalized['field']);
        $this->assertSame('Situação', $normalized['label']);
        $this->assertSame('select', $normalized['colsFilterType']);
    }

    #[Test]
    public function a_relation_filter_keeps_its_relation_field_and_aggregate(): void
    {
        $filter = (new FilterWizard(new ScriptedCommand([
            'total', 'Total de pedidos', 'number', '>',
            true, 'orders', 'amount', 'SUM', // whereHas: relação, campo, agregado
            true,                            // salvar
        ])))->run();

        $normalized = FilterRule::normalize($filter);

        $this->assertSame('orders', $normalized['whereHas']);
        $this->assertSame('amount', $normalized['field_relation']);
        $this->assertSame('SUM', $normalized['aggregate']);
    }
}
