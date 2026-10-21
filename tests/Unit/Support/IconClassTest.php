<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\IconClass;

/** 1.50.0: `icon=bx-check` (as the docs wrote it) printed an icon that never showed. */
class IconClassTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function cases(): array
    {
        return [
            'short boxicon' => ['bx-check', 'bx bx-check'],
            'solid' => ['bxs-star', 'bx bxs-star'],
            'logo' => ['bxl-github', 'bx bxl-github'],
            'already full' => ['bx bx-check', 'bx bx-check'],
            'fontawesome' => ['fas fa-user', 'fas fa-user'],
            'empty' => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_adds_the_boxicons_base_only_when_missing(string $in, string $out): void
    {
        $this->assertSame($out, IconClass::normalize($in));
    }
}
