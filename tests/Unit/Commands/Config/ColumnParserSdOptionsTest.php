<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Tests\TestCase;

/**
 * Covers the SearchDropdown `--column` options added to ColumnParser's
 * $keyMap for the BaseCrud configuration surface: sd_init_with_data (boolean
 * cast), sd_label_three, sd_mask_one/two/three, sd_array_search (comma-split,
 * mirroring upload_allowed_types), sd_start_list, sd_depends_on and
 * sd_filter_column (cascading dropdown).
 */
class ColumnParserSdOptionsTest extends TestCase
{
    private function parser(): ColumnParser
    {
        return new ColumnParser;
    }

    #[Test]
    public function sd_init_with_data_true_casts_to_boolean(): void
    {
        $config = $this->parser()->parse('supplier_id:searchdropdown:sd_init_with_data=true');

        $this->assertTrue($config['colsSDInitWithData']);
    }

    #[Test]
    public function sd_init_with_data_false_casts_to_boolean(): void
    {
        $config = $this->parser()->parse('supplier_id:searchdropdown:sd_init_with_data=false');

        $this->assertFalse($config['colsSDInitWithData']);
    }

    #[Test]
    public function sd_label_three_maps_to_the_canonical_key(): void
    {
        $config = $this->parser()->parse('supplier_id:searchdropdown:sd_label_three=city');

        $this->assertSame('city', $config['colsSDLabelThree']);
    }

    #[Test]
    public function sd_mask_one_two_three_map_to_the_canonical_keys(): void
    {
        $config = $this->parser()->parse(
            'supplier_id:searchdropdown:sd_mask_one=cnpj:sd_mask_two=phone:sd_mask_three=date'
        );

        $this->assertSame('cnpj', $config['colsSDMaskOne']);
        $this->assertSame('phone', $config['colsSDMaskTwo']);
        $this->assertSame('date', $config['colsSDMaskThree']);
    }

    #[Test]
    public function sd_array_search_splits_a_csv_string_into_an_array(): void
    {
        $config = $this->parser()->parse('supplier_id:searchdropdown:sd_array_search=cnpj,email, phone');

        $this->assertSame(['cnpj', 'email', 'phone'], $config['colsSDArraySearch']);
    }

    #[Test]
    public function sd_start_list_maps_to_the_canonical_key(): void
    {
        $config = $this->parser()->parse('supplier_id:searchdropdown:sd_start_list=top');

        $this->assertSame('top', $config['colsSDStartList']);
    }

    #[Test]
    public function sd_depends_on_and_sd_filter_column_map_to_the_canonical_keys(): void
    {
        $config = $this->parser()->parse(
            'city_id:searchdropdown:sd_depends_on=state_id:sd_filter_column=state_id'
        );

        $this->assertSame('state_id', $config['colsSDDependsOn']);
        $this->assertSame('state_id', $config['colsSDFilterColumn']);
    }
}
