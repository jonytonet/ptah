<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Support\ColumnKeys;
use Ptah\Tests\TestCase;
use ReflectionMethod;

/**
 * 1.47.0: the docs promised `colsMaskEmptyValue`, `colsUploadDisk` and
 * `colsHelper` (for help text) — nothing read them, and nothing said so.
 * ColumnKeys::KNOWN is the single list; this keeps it equal to the code,
 * and keeps the docs inside it.
 */
class ColumnKeysTest extends TestCase
{
    /**
     * Keys a doc mentions ON PURPOSE as not read — the sentence around them
     * says so. Anything else a doc names must be a key ptah reads.
     */
    private const DOCUMENTED_AS_NOT_READ = [
        'colsOptions' => 'Configuration.md: "is not read by ptah and is rejected by the import validator"',
        'colsMaskDecimalPlaces' => 'KnownLimitations.md: listed among keys the runtime does not read',
        'colsMaskRegex' => 'KnownLimitations.md: listed among keys the runtime does not read',
        'colsFilter' => 'Configuration.md: the `colsFilter*` family a table listed before 1.41.4',
        'colsFilterField' => 'KnownLimitations.md: what the wizard emitted before FilterRule',
        'colsRelationTable' => 'KnownLimitations.md: the wizard gap (WizardKeyReachabilityTest)',
        'colsRelationJoinColumn' => 'KnownLimitations.md: the wizard gap (WizardKeyReachabilityTest)',
        'colsRelationDisplayColumn' => 'KnownLimitations.md: the wizard gap (WizardKeyReachabilityTest)',
        'colsValidation' => 'Configuration.md: wizard transcript, with the note that nothing reads it',
        'colsTotal' => 'Configuration.md: the Totalizer note saying it is not read',
    ];

    /**
     * Quoted 'colsX' keys in the RUNTIME readers — the same definition
     * ConfigKeyReachabilityTest uses (writers, commands and validators excluded,
     * comments stripped), so the two lists cannot drift apart.
     *
     * @return array<string, true>
     */
    private static function keysInCode(): array
    {
        $readers = (new ReflectionMethod(ConfigKeyReachabilityTest::class, 'readers'))->invoke(null);
        $found = [];
        foreach ($readers as $code) {
            preg_match_all("/'(cols[A-Z][A-Za-z]*)'/", $code, $m);
            foreach ($m[1] as $key) {
                $found[$key] = true;
            }
        }

        return $found;
    }

    #[Test]
    public function the_list_matches_the_keys_the_code_reads(): void
    {
        $code = array_keys(self::keysInCode());
        sort($code);
        $known = ColumnKeys::KNOWN;
        sort($known);

        $this->assertSame([], array_values(array_diff($code, $known)), 'Chave com leitor no runtime e ausente de ColumnKeys::KNOWN.');
        $this->assertSame([], array_values(array_diff($known, $code)), 'Chave em ColumnKeys::KNOWN sem leitor no runtime.');
    }

    #[Test]
    public function every_key_a_doc_names_is_read_by_ptah(): void
    {
        $root = dirname(__DIR__, 3);
        $missing = [];
        foreach ([...glob("{$root}/docs/*.md") ?: [], "{$root}/README.md"] as $doc) {
            preg_match_all('/\b(cols[A-Z][A-Za-z]*)\b/', (string) file_get_contents($doc), $m);
            foreach (array_unique($m[1]) as $key) {
                if (! ColumnKeys::isKnown($key) && ! array_key_exists($key, self::DOCUMENTED_AS_NOT_READ)) {
                    $missing[] = basename($doc).": {$key}";
                }
            }
        }

        $this->assertSame([], $missing, 'A doc cita chave que o ptah nao le.');
    }

    #[Test]
    public function the_documented_column_types_are_the_ones_the_form_accepts(): void
    {
        $doc = (string) file_get_contents(dirname(__DIR__, 3).'/docs/Configuration.md');
        preg_match('/^\| `colsTipo` \|.*$/m', $doc, $row);

        foreach (CrudConfigEnums::COLUMN_TYPES as $type) {
            $this->assertStringContainsString("`{$type}`", $row[0] ?? '', "colsTipo `{$type}` fora da doc.");
        }
    }
}
