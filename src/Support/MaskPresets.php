<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Ready-made mask sets, shipped as DATA and activated by the host.
 *
 * The registry exists so the package does not decide what a document looks
 * like. This class is the other half of that bargain: not having to hand-write
 * `000.000.000-00` in every new project is a real convenience, and refusing to
 * ship the definitions at all would just move the copy-pasting.
 *
 * ── Why a preset and not the locale ──────────────────────────────────────
 *
 * The obvious shortcut is to register the Brazilian masks when the application
 * runs in `pt_BR`. It is the wrong key, for three reasons:
 *
 * 1. A locale is an interface LANGUAGE, not a jurisdiction. A Brazilian company
 *    running its ERP in English still needs CPF; a Portuguese company running
 *    pt_BR does not have one — it has NIF.
 *
 * 2. A locale changes per request. A user switching the interface to English
 *    would lose the mask mid-session, and a document already stored would stop
 *    being formatted on the edit screen. Which masks exist is a property of the
 *    application, not of who is looking at it.
 *
 * 3. It quietly hands the maintenance liability back to the package: with the
 *    set activated by inference, a format change becomes a ptah release for
 *    everyone rather than a decision by the host that is affected.
 *
 * So the host activates a set, explicitly. A host that WANTS the locale to
 * decide has the whole condition available to it, with its own criteria:
 *
 *     if (app()->getLocale() === 'pt_BR') {
 *         PtahMask::preset('br');
 *     }
 *
 * ── The honest warning ───────────────────────────────────────────────────
 *
 * A preset is best-effort data about someone else's law. Brazil's CNPJ became
 * alphanumeric in 2026; the next change will not announce itself either. A host
 * that cannot afford to wait for a ptah release overrides the one mask it cares
 * about in `config/ptah-masks.php`, which wins over the preset by design.
 */
final class MaskPresets
{
    /**
     * Every set this version ships, by name.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function all(): array
    {
        return [
            'br' => self::br(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public static function get(string $name): ?array
    {
        return self::all()[strtolower(trim($name))] ?? null;
    }

    /**
     * Brazil.
     *
     * The names match what `CrudConfigEnums::MASKS` used to list, so a host that
     * already has `colsMask: "cpf"` in a live crud_config gets the behaviour it
     * was always promised by enabling this set — and nothing else to change.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function br(): array
    {
        return [
            'cpf' => [
                'pattern' => '000.000.000-00',
                'store' => 'digits',
            ],

            // Alphanumeric since 2026: twelve alphanumeric positions plus two
            // numeric check digits. `alnum` rather than `digits`, or the letters
            // introduced by that change would be stripped on the way to the
            // database.
            'cnpj' => [
                'pattern' => '**.***.***'.'/'.'****-00',
                'store' => 'alnum',
            ],

            // One field that takes either, for a "customer" who may be a person
            // or a company. The shorter shape wins while it still fits.
            'documento' => [
                'pattern' => ['000.000.000-00', '**.***.***'.'/'.'****-00'],
                'store' => 'alnum',
            ],

            'cep' => [
                'pattern' => '00000-000',
                'store' => 'digits',
            ],

            // Landline and mobile in the same field.
            'telefone' => [
                'pattern' => ['(00) 0000-0000', '(00) 00000-0000'],
                'store' => 'digits',
            ],

            // Old format and Mercosul.
            'placa' => [
                'pattern' => ['AAA-0000', 'AAA0A00'],
                'store' => 'upper',
            ],

            'pis' => [
                'pattern' => '000.00000.00-0',
                'store' => 'digits',
            ],

            'inscricao_estadual' => [
                'pattern' => '000.000.000.000',
                'store' => 'digits',
            ],
        ];
    }
}
