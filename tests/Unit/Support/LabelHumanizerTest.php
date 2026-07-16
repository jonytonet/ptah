<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\LabelHumanizer;
use Ptah\Tests\TestCase;

/**
 * LabelHumanizer is the single label derivation shared by ptah:forge and
 * ptah:config — strips the FK marker, applies the pt-BR dictionary, and title-cases
 * the rest.
 */
class LabelHumanizerTest extends TestCase
{
    #[Test]
    public function strips_the_fk_marker(): void
    {
        // Was "Category Id" via the old ColumnParser path.
        $this->assertSame('Category', LabelHumanizer::make('category_id'));
    }

    #[Test]
    public function applies_the_ptbr_dictionary_with_accents(): void
    {
        $this->assertSame('Usuário', LabelHumanizer::make('usuario'));
        $this->assertSame('Observações', LabelHumanizer::make('observacoes'));
        $this->assertSame('E-mail', LabelHumanizer::make('email'));
        $this->assertSame('CNPJ', LabelHumanizer::make('cnpj'));
        $this->assertSame('Data de Nascimento', LabelHumanizer::make('data_nascimento'));
    }

    #[Test]
    public function title_cases_unknown_fields(): void
    {
        $this->assertSame('Product Name', LabelHumanizer::make('product_name'));
    }

    #[Test]
    public function config_dictionary_overrides_and_extends(): void
    {
        config(['ptah.crud.label_dictionary' => ['valor_total' => 'Valor Total', 'nome' => 'Nome Completo']]);

        $this->assertSame('Valor Total', LabelHumanizer::make('valor_total')); // new entry
        $this->assertSame('Nome Completo', LabelHumanizer::make('nome'));       // overrides built-in
    }
}
