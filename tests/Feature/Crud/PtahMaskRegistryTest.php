<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;

/**
 * The mask registry: named masks, defined by the host.
 *
 * The package used to ship `cpf`, `cnpj`, `rg`, `pis`, `ncm`, `ean13`, `cep` and
 * `plate` in `CrudConfigEnums::MASKS` — all Brazilian, all accepted by the
 * validator, and none of them implemented. A column configured `colsMask: "cpf"`
 * rendered a plain text input and stored whatever was typed. So the package was
 * carrying a country's document formats as a maintenance liability while
 * delivering none of the behaviour, and Brazil's CNPJ going alphanumeric in 2026
 * would have been a breaking change in a package used elsewhere.
 *
 * Now the package ships two masks that are nobody's law, and the host declares
 * the rest. These tests are mostly about the pattern engine, because that is
 * what makes a declarative mask possible at all: the package does not know what
 * a CPF is, it knows how to apply `000.000.000-00`.
 */
class PtahMaskRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PtahMask::flush();
        config(['ptah-masks' => []]);
    }

    protected function tearDown(): void
    {
        PtahMask::flush();

        parent::tearDown();
    }

    // ── O que o pacote embute, e o que nao ────────────────────────────────

    #[Test]
    public function the_package_ships_only_masks_that_are_nobody_s_law(): void
    {
        $names = array_keys(PtahMask::all());

        $this->assertEqualsCanonicalizing(['digits', 'decimal'], $names);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function countrySpecificProvider(): array
    {
        return [
            'cpf' => ['cpf'],
            'cnpj' => ['cnpj'],
            'cep' => ['cep'],
            'rg' => ['rg'],
            'pis' => ['pis'],
            'plate' => ['plate'],
            'ssn' => ['ssn'],
        ];
    }

    #[Test]
    #[DataProvider('countrySpecificProvider')]
    public function a_country_specific_mask_is_not_registered_until_the_host_says_so(string $name): void
    {
        // The point of the whole change. A document format is the host's, and a
        // package release is not where a law changing should land.
        $this->assertFalse(PtahMask::has($name));
        $this->assertNull(PtahMask::get($name));
    }

    #[Test]
    public function an_unregistered_mask_never_rewrites_the_value(): void
    {
        // Because the form falls back to a plain input for it, the value that
        // arrives is whatever was typed — and silently stripping it would be
        // worse than storing it.
        $this->assertSame('055.465.309-52', PtahMask::store('cpf', '055.465.309-52'));
        $this->assertSame('qualquer coisa', PtahMask::store(null, 'qualquer coisa'));
    }

    // ── Definir ───────────────────────────────────────────────────────────

    #[Test]
    public function the_host_defines_a_mask_and_it_becomes_referenceable(): void
    {
        PtahMask::define('cpf', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        $this->assertTrue(PtahMask::has('cpf'));
        $this->assertContains('cpf', PtahMask::names());
        $this->assertSame('000.000.000-00', PtahMask::get('cpf')['placeholder']);
    }

    #[Test]
    public function a_definition_can_come_from_the_config_file(): void
    {
        config(['ptah-masks' => ['cep' => ['pattern' => '00000-000', 'store' => 'digits']]]);

        $this->assertTrue(PtahMask::has('cep'));
        $this->assertSame('01310-100', PtahMask::format('cep', '01310100'));
    }

    #[Test]
    public function define_wins_over_the_config_file_which_wins_over_the_built_ins(): void
    {
        // The built-ins are a floor, not a fence: a host that wants `decimal` to
        // mean something else says so and is obeyed.
        config(['ptah-masks' => ['decimal' => ['pattern' => '0,00', 'store' => 'raw']]]);

        $this->assertSame('raw', PtahMask::get('decimal')['store']);

        PtahMask::define('decimal', ['pattern' => '0.00', 'store' => 'trim']);

        $this->assertSame('trim', PtahMask::get('decimal')['store']);
    }

    #[Test]
    public function the_name_is_matched_loosely(): void
    {
        PtahMask::define('  CPF  ', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        $this->assertTrue(PtahMask::has('cpf'));
        $this->assertTrue(PtahMask::has('CPF'));
    }

    #[Test]
    public function a_definition_with_a_store_rule_nobody_implements_is_rejected(): void
    {
        // Not accepted-and-ignored: a store rule that does nothing would look
        // like it was cleaning the value while passing it straight through.
        PtahMask::define('estranha', ['pattern' => '000', 'store' => 'faz_magica']);

        $this->assertFalse(PtahMask::has('estranha'));
    }

    #[Test]
    public function a_closure_store_rule_works_through_define(): void
    {
        PtahMask::define('sku', [
            'pattern' => 'AAA-0000',
            'store' => fn (mixed $v): string => 'SKU:'.preg_replace('/\W/', '', (string) $v),
        ]);

        $this->assertSame('SKU:ABC1234', PtahMask::store('sku', 'ABC-1234'));
    }

    // ── O motor de pattern ────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function patternProvider(): array
    {
        return [
            'cpf completo' => ['000.000.000-00', '05546530952', '055.465.309-52'],
            'cpf parcial' => ['000.000.000-00', '05546', '055.46'],
            'cpf com pontuacao colada' => ['000.000.000-00', '055.465.309-52', '055.465.309-52'],
            'cep' => ['00000-000', '01310100', '01310-100'],
            'letras' => ['AAA-0000', 'ABC1234', 'ABC-1234'],
            'alfanumerico' => ['**.***.***', '12ab34cd56', '12.ab3.4cd'],
            'literal no inicio' => ['(00) 0000', '1133224455', '(11) 3322'],
            'um caractere' => ['000.000.000-00', '0', '0'],
        ];
    }

    #[Test]
    #[DataProvider('patternProvider')]
    public function the_pattern_engine_formats_what_it_is_given(string $pattern, string $input, string $expected): void
    {
        PtahMask::define('p', ['pattern' => $pattern, 'store' => 'raw']);

        $this->assertSame($expected, PtahMask::format('p', $input));
    }

    #[Test]
    public function a_half_typed_value_does_not_end_in_a_dangling_separator(): void
    {
        // Emitting the literal before there is anything after it makes the field
        // read "055." while the third digit is still being typed.
        PtahMask::define('cpf', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        $this->assertSame('055', PtahMask::format('cpf', '055'));
        $this->assertSame('055.4', PtahMask::format('cpf', '0554'));
    }

    #[Test]
    public function a_character_that_cannot_fill_a_slot_is_skipped_not_dropped(): void
    {
        // Pasting "(11) 98765-4321" into a digits pattern must keep the digits
        // and discard the punctuation, instead of spending slots on brackets.
        PtahMask::define('tel', ['pattern' => '(00) 00000-0000', 'store' => 'digits']);

        $this->assertSame('(11) 98765-4321', PtahMask::format('tel', '(11) 98765-4321'));
        $this->assertSame('(11) 98765-4321', PtahMask::format('tel', '11987654321'));
    }

    #[Test]
    public function a_letter_pasted_into_a_digit_pattern_is_discarded(): void
    {
        PtahMask::define('cpf', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        $this->assertSame('055.465', PtahMask::format('cpf', '0abc55465'));
    }

    // ── Varios patterns numa mascara ──────────────────────────────────────

    #[Test]
    public function the_smallest_pattern_that_still_fits_is_the_one_used(): void
    {
        // The only way a phone field is usable in a country with both eight- and
        // nine-digit numbers.
        PtahMask::define('tel', [
            'pattern' => ['(00) 0000-0000', '(00) 00000-0000'],
            'store' => 'digits',
        ]);

        $this->assertSame('(11) 3322-4455', PtahMask::format('tel', '1133224455'));
        $this->assertSame('(11) 98765-4321', PtahMask::format('tel', '11987654321'));
    }

    #[Test]
    public function one_field_can_take_two_document_shapes(): void
    {
        // A single "documento" column holding either a CPF or a CNPJ, which is
        // what a person-or-company field actually is.
        PtahMask::define('documento', [
            'pattern' => ['000.000.000-00', '00.000.000/0000-00'],
            'store' => 'digits',
        ]);

        $this->assertSame('055.465.309-52', PtahMask::format('documento', '05546530952'));
        $this->assertSame('12.345.678/0001-95', PtahMask::format('documento', '12345678000195'));
    }

    #[Test]
    public function the_maxlength_covers_the_longest_shape(): void
    {
        // A maxlength taken from the first pattern would make the longer shape
        // impossible to finish typing.
        PtahMask::define('tel', [
            'pattern' => ['(00) 0000-0000', '(00) 00000-0000'],
            'store' => 'digits',
        ]);

        $this->assertSame(15, PtahMask::get('tel')['maxlength']);
    }

    // ── Gravacao ──────────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string, 2: mixed}>
     */
    public static function storeProvider(): array
    {
        return [
            'digits' => ['digits', '055.465.309-52', '05546530952'],
            'alnum' => ['alnum', '12.ABC.345/01DE-35', '12ABC34501DE35'],
            'upper' => ['upper', ' abc-1d23 ', 'ABC-1D23'],
            'lower' => ['lower', ' ABC ', 'abc'],
            'trim' => ['trim', '  x  ', 'x'],
            'raw' => ['raw', ' 055.465 ', ' 055.465 '],
        ];
    }

    #[Test]
    #[DataProvider('storeProvider')]
    public function a_named_store_rule_decides_what_reaches_the_database(string $rule, string $input, mixed $expected): void
    {
        PtahMask::define('m', ['pattern' => '000', 'store' => $rule]);

        $this->assertSame($expected, PtahMask::store('m', $input));
    }

    /**
     * @return array<string, array{0: string, 1: float}>
     */
    public static function decimalProvider(): array
    {
        return [
            'br' => ['R$ 1.253,08', 1253.08],
            'en' => ['$1,253.08', 1253.08],
            'so virgula decimal' => ['25,50', 25.5],
            'virgula de milhar' => ['1,000', 1000.0],
            'so ponto' => ['25.50', 25.5],
            'sem separador' => ['2550', 2550.0],
            'vazio' => ['', 0.0],
            'lixo' => ['abc', 0.0],
        ];
    }

    #[Test]
    #[DataProvider('decimalProvider')]
    public function the_decimal_rule_reads_either_convention(string $input, float $expected): void
    {
        // Whichever separator comes LAST is the decimal mark, so "1.234,56" and
        // "1,234.56" land on the same number instead of one of them becoming
        // 123456.
        $this->assertSame($expected, PtahMask::store('decimal', $input));
    }

    // ── Teclado ───────────────────────────────────────────────────────────

    #[Test]
    public function a_pattern_with_letters_does_not_force_the_numeric_keyboard(): void
    {
        // Forcing `inputmode=numeric` on a field that accepts letters makes it
        // impossible to fill on a touch device.
        PtahMask::define('digitos', ['pattern' => '000-000', 'store' => 'digits']);
        PtahMask::define('placa', ['pattern' => 'AAA-0A00', 'store' => 'upper']);

        $this->assertSame('numeric', PtahMask::get('digitos')['inputmode']);
        $this->assertSame('text', PtahMask::get('placa')['inputmode']);
    }

    #[Test]
    public function an_explicit_inputmode_is_obeyed(): void
    {
        PtahMask::define('m', ['pattern' => '000', 'store' => 'digits', 'inputmode' => 'tel']);

        $this->assertSame('tel', PtahMask::get('m')['inputmode']);
    }

    // ── Compatibilidade ───────────────────────────────────────────────────

    #[Test]
    public function the_two_masks_that_always_worked_are_still_referenceable(): void
    {
        // `money_brl` and `uppercase` are the only names that had real behaviour
        // before the registry, and hosts have them in live crud_configs. They
        // keep their own hand-written controls; the registry only has to admit
        // that the names are valid.
        $this->assertContains('money_brl', PtahMask::names());
        $this->assertContains('uppercase', PtahMask::names());
    }

    // ── Presets ───────────────────────────────────────────────────────────

    #[Test]
    public function no_preset_is_active_until_the_host_says_so(): void
    {
        $this->assertEqualsCanonicalizing(['digits', 'decimal'], array_keys(PtahMask::all()));
    }

    #[Test]
    public function a_preset_named_in_the_config_is_activated(): void
    {
        config(['ptah-masks' => ['presets' => ['br']]]);

        $this->assertTrue(PtahMask::has('cpf'));
        $this->assertTrue(PtahMask::has('cnpj'));
        $this->assertSame('055.465.309-52', PtahMask::format('cpf', '05546530952'));
    }

    #[Test]
    public function a_preset_can_be_activated_at_runtime(): void
    {
        // The escape hatch for a host that wants its own condition — the locale,
        // a tenant, a feature flag. The CRITERION is the host's; the data is the
        // package's.
        $this->assertTrue(PtahMask::preset('br'));
        $this->assertTrue(PtahMask::has('cep'));
    }

    #[Test]
    public function the_2026_cnpj_keeps_its_letters_on_the_way_to_the_database(): void
    {
        // `digits` would strip exactly what the alphanumeric CNPJ added, and the
        // stored value would be shorter than the document.
        PtahMask::preset('br');

        $this->assertSame('12ABC34501DE35', PtahMask::store('cnpj', '12.ABC.345/01DE-35'));
    }

    #[Test]
    public function the_preset_document_field_takes_either_shape(): void
    {
        PtahMask::preset('br');

        $this->assertSame('055.465.309-52', PtahMask::format('documento', '05546530952'));
        $this->assertSame('12.345.678/0001-95', PtahMask::format('documento', '12345678000195'));
    }

    #[Test]
    public function the_host_config_overrides_a_preset(): void
    {
        // The reason a preset is safe to ship: when a format moves before the
        // next ptah release, the host overrides the one mask it cares about.
        config(['ptah-masks' => [
            'presets' => ['br'],
            'cpf' => ['pattern' => '00000000000', 'store' => 'digits'],
        ]]);

        $this->assertSame('05546530952', PtahMask::format('cpf', '055.465.309-52'));
    }

    #[Test]
    public function define_overrides_a_preset_too(): void
    {
        PtahMask::preset('br');
        PtahMask::define('cep', ['pattern' => '00000', 'store' => 'digits']);

        $this->assertSame('01310', PtahMask::format('cep', '01310100'));
    }

    #[Test]
    public function the_presets_key_is_not_mistaken_for_a_mask(): void
    {
        // It is a control key of the file. Without the guard it would be
        // normalised as a definition and rejected, which works by accident
        // rather than on purpose.
        config(['ptah-masks' => ['presets' => ['br']]]);

        $this->assertFalse(PtahMask::has('presets'));
        $this->assertNotContains('presets', PtahMask::names());
    }

    #[Test]
    public function an_unknown_preset_is_ignored_rather_than_fatal(): void
    {
        // A preset name that a later version drops must not take a boot
        // sequence down with it.
        $this->assertFalse(PtahMask::preset('narnia'));

        config(['ptah-masks' => ['presets' => ['narnia', 'br']]]);

        $this->assertTrue(PtahMask::has('cpf'), 'O preset valido ao lado tem de continuar valendo.');
    }

    #[Test]
    public function the_preset_names_the_masks_the_old_enum_promised(): void
    {
        // A host with `colsMask: "cpf"` in a live crud_config gets the behaviour
        // it was always promised by enabling the set — and nothing else to
        // change.
        PtahMask::preset('br');

        foreach (['cpf', 'cnpj', 'cep', 'pis'] as $legacy) {
            $this->assertTrue(PtahMask::has($legacy), "`{$legacy}` deveria vir no preset br.");
        }
    }

    #[Test]
    public function a_mask_with_no_pattern_still_has_a_store_rule(): void
    {
        // `digits` and `decimal` are store-only: there is no display shape for
        // "just the digits", so the form leaves them as a plain input and the
        // normalisation happens on save.
        $this->assertSame([], PtahMask::get('digits')['patterns']);
        $this->assertSame('05546530952', PtahMask::store('digits', '055.465.309-52'));
        $this->assertSame('055.465.309-52', PtahMask::format('digits', '055.465.309-52'));
    }
}
