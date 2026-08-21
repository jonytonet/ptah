<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * FIX 4 of the Onda C ux-acl-tree audit: <x-forge-empty>, a reusable empty
 * state for custom screens outside the BaseCrud table. Tokenized
 * (.ptah-c-empty_box/_ttl/_sub) — the same classes the BaseCrud table's own
 * empty state (_table.blade.php) already used, and now also builds on top of
 * this component, so both stay visually identical.
 */
class ForgeEmptyTest extends TestCase
{
    #[Test]
    public function renders_title_and_description_with_the_tokenized_classes(): void
    {
        $html = (string) $this->blade(
            '<x-forge-empty :title="$title" :description="$description" />',
            ['title' => 'Nothing here yet', 'description' => 'Create the first record.']
        );

        $this->assertStringContainsString('Nothing here yet', $html);
        $this->assertStringContainsString('Create the first record.', $html);
        $this->assertStringContainsString('ptah-c-empty_box', $html);
        $this->assertStringContainsString('ptah-c-empty_ttl', $html);
        $this->assertStringContainsString('ptah-c-empty_sub', $html);
    }

    #[Test]
    public function falls_back_to_a_default_icon_when_no_icon_slot_is_given(): void
    {
        $html = (string) $this->blade('<x-forge-empty title="Empty" />');

        $this->assertMatchesRegularExpression('/ptah-c-empty_box">\s*<svg/', $html);
    }

    #[Test]
    public function renders_a_custom_icon_slot_instead_of_the_default(): void
    {
        $html = (string) $this->blade(
            '<x-forge-empty title="Empty"><x-slot:icon><svg data-testid="custom-icon"></svg></x-slot:icon></x-forge-empty>'
        );

        $this->assertStringContainsString('data-testid="custom-icon"', $html);
    }

    #[Test]
    public function renders_the_cta_slot_only_when_provided(): void
    {
        $withoutCta = (string) $this->blade('<x-forge-empty title="Empty" />');
        $this->assertStringNotContainsString('ptah-cta-marker', $withoutCta);

        $withCta = (string) $this->blade(
            '<x-forge-empty title="Empty"><x-slot:cta><button class="ptah-cta-marker">New</button></x-slot:cta></x-forge-empty>'
        );
        $this->assertStringContainsString('ptah-cta-marker', $withCta);
    }
}
