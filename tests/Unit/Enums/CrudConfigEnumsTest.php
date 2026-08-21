<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Enums;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Tests\TestCase;

/**
 * Covers CrudConfigEnums' observable behaviour: the isValid*() helpers, and
 * the OPERATORS vs STYLE_CONDITIONS split (Onda 4 Parte B bug fix — see
 * ConfigValidatorTest for the full regression coverage of that split).
 */
class CrudConfigEnumsTest extends TestCase
{
    #[Test]
    public function is_valid_column_type_accepts_known_types_and_rejects_unknown_ones(): void
    {
        $this->assertTrue(CrudConfigEnums::isValidColumnType('text'));
        $this->assertTrue(CrudConfigEnums::isValidColumnType('searchdropdown'));
        $this->assertFalse(CrudConfigEnums::isValidColumnType('bogus'));
    }

    #[Test]
    public function is_valid_renderer_accepts_known_renderers_and_rejects_unknown_ones(): void
    {
        $this->assertTrue(CrudConfigEnums::isValidRenderer('badge'));
        $this->assertTrue(CrudConfigEnums::isValidRenderer('qrcode'));
        $this->assertFalse(CrudConfigEnums::isValidRenderer('bogus'));
    }

    #[Test]
    public function is_valid_mask_accepts_known_masks_and_rejects_unknown_ones(): void
    {
        $this->assertTrue(CrudConfigEnums::isValidMask('cpf'));
        $this->assertTrue(CrudConfigEnums::isValidMask('custom_regex'));
        $this->assertFalse(CrudConfigEnums::isValidMask('bogus'));
    }

    #[Test]
    public function is_valid_operator_accepts_known_operators_and_rejects_unknown_ones(): void
    {
        $this->assertTrue(CrudConfigEnums::isValidOperator('='));
        $this->assertTrue(CrudConfigEnums::isValidOperator('LIKE'));
        $this->assertFalse(CrudConfigEnums::isValidOperator('bogus'));
    }

    /**
     * OPERATORS is the SQL-filter list (consumed literally by FilterService /
     * TextFilterStrategy / RelationFilterStrategy, e.g. `whereRaw('... LIKE ?')`)
     * and must keep '=' + 'LIKE'. STYLE_CONDITIONS is the PHP-comparison list
     * HasCrudRenderers::getRowStyle()'s match() actually understands, and must
     * NOT accidentally re-grow '=' or 'LIKE' — see ConfigValidatorTest for why
     * that combination silently breaks conditionStyles at render time.
     */
    #[Test]
    public function operators_and_style_conditions_are_deliberately_different_lists(): void
    {
        $this->assertContains('=', CrudConfigEnums::OPERATORS);
        $this->assertContains('LIKE', CrudConfigEnums::OPERATORS);

        $this->assertNotContains('=', CrudConfigEnums::STYLE_CONDITIONS);
        $this->assertNotContains('LIKE', CrudConfigEnums::STYLE_CONDITIONS);
        $this->assertContains('==', CrudConfigEnums::STYLE_CONDITIONS);
    }
}
