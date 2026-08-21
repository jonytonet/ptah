<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * FIX 4 of the Onda C ux-acl-tree audit: <x-forge-skeleton>, a reusable
 * loading placeholder for custom screens outside the BaseCrud table (which
 * already has its own thin loading bar). Every variant is decorative
 * (aria-hidden) and pulses via Tailwind's `animate-pulse`, which the
 * package's global prefers-reduced-motion rule already freezes.
 */
class ForgeSkeletonTest extends TestCase
{
    #[Test]
    public function default_variant_is_text_and_renders_one_pulsing_line(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton />');

        $this->assertSame(1, substr_count($html, 'ptah-c-skel'));
        $this->assertStringContainsString('animate-pulse', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    #[Test]
    public function text_variant_repeats_the_requested_count(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="text" :count="3" />');

        $this->assertSame(3, substr_count($html, 'ptah-c-skel'));
    }

    #[Test]
    public function title_variant_renders_a_single_wide_block(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="title" />');

        $this->assertStringContainsString('ptah-c-skel', $html);
        $this->assertStringContainsString('h-5', $html);
    }

    #[Test]
    public function avatar_variant_renders_a_circle(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="avatar" />');

        $this->assertStringContainsString('rounded-full', $html);
    }

    #[Test]
    public function card_variant_uses_the_card_token_and_several_lines(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="card" />');

        $this->assertStringContainsString('ptah-c-skel_card', $html);
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'ptah-c-skel '));
    }

    #[Test]
    public function table_row_variant_renders_three_columns(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="table-row" />');

        $this->assertSame(3, substr_count($html, 'ptah-c-skel '));
    }

    #[Test]
    public function an_unknown_variant_falls_back_to_text(): void
    {
        $html = (string) $this->blade('<x-forge-skeleton variant="not-a-real-variant" />');

        $this->assertStringContainsString('ptah-c-skel', $html);
        $this->assertSame(1, substr_count($html, 'ptah-c-skel'));
    }
}
