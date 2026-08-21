<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Wizards\StyleWizard;
use Ptah\Tests\TestCase;

/**
 * Double of an Artisan Command that answers ask()/choice()/confirm() from a
 * pre-loaded queue instead of touching the console, and no-ops every other
 * output method the wizard calls along the way.
 */
class StyleWizardCommandDouble extends Command
{
    /** @var array<int, mixed> */
    private array $answers;

    public function __construct(array $answers)
    {
        parent::__construct();
        $this->answers = $answers;
    }

    public function ask($question, $default = null, $validator = null)
    {
        return array_shift($this->answers);
    }

    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = null)
    {
        return array_shift($this->answers);
    }

    public function confirm($question, $default = false)
    {
        return array_shift($this->answers);
    }

    public function info($string, $verbosity = null): void {}

    public function warn($string, $verbosity = null): void {}

    public function line($string, $style = null, $verbosity = null): void {}

    public function newLine($count = 1): void {}

    public function table($headers, $rows, $tableStyle = 'default', array $columnStyles = []): void {}
}

/**
 * Covers StyleWizard — the interactive counterpart to `--style=`. Must emit
 * the same canonical shape {field, condition, value, style} StyleParser
 * produces, built from CrudConfigEnums::STYLE_CONDITIONS (the PHP-style
 * operators HasCrudRenderers::getRowStyle() actually evaluates).
 */
class StyleWizardTest extends TestCase
{
    #[Test]
    public function emits_the_canonical_shape(): void
    {
        $command = new StyleWizardCommandDouble([
            'status',       // field
            '==',           // condition (choice)
            'cancelled',    // value
            '#FEE2E2',      // background color
            '#991B1B',      // text color
            'bold',         // font weight (choice)
            '',             // custom css
            true,           // confirm save
        ]);

        $style = (new StyleWizard($command))->run();

        $this->assertSame([
            'field' => 'status',
            'condition' => '==',
            'value' => 'cancelled',
            'style' => 'background:#FEE2E2;color:#991B1B;font-weight:bold;',
        ], $style);
    }

    #[Test]
    public function css_is_assembled_skipping_blank_answers(): void
    {
        $command = new StyleWizardCommandDouble([
            'amount',
            '>',
            '1000',
            '',             // background color left blank
            'red',          // text color
            '',             // font weight left blank
            'border:2px solid red', // custom css
            true,
        ]);

        $style = (new StyleWizard($command))->run();

        $this->assertSame('color:red;border:2px solid red;', $style['style']);
    }

    #[Test]
    public function empty_field_returns_null_without_asking_further_questions(): void
    {
        $command = new StyleWizardCommandDouble(['']);

        $style = (new StyleWizard($command))->run();

        $this->assertNull($style);
    }

    #[Test]
    public function declining_to_save_reruns_the_wizard_with_the_previous_answers(): void
    {
        $command = new StyleWizardCommandDouble([
            'status', '==', 'cancelled', '', '', '', '', false, // first pass, declined
            'status', '==', 'cancelled', '', '', '', '', true,  // second pass, confirmed
        ]);

        $style = (new StyleWizard($command))->run();

        $this->assertSame('status', $style['field']);
    }
}
