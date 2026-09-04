<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the FIX 6 accessibility wins added to forge-pagination.blade.php:
 * a labelled <nav>, aria-label on the icon-only prev/next chevrons,
 * aria-current="page" on the active page button, and a legible icon color
 * for the disabled chevrons (previously text-gray-300/dark:text-slate-600,
 * 1.47:1 / 2.36:1 — both far under the 3:1 icon floor).
 */
class ForgePaginationAccessibilityTest extends TestCase
{
    private function render(int $currentPage, int $total = 100, int $perPage = 10): string
    {
        $items = array_fill(0, $perPage, 'x');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $currentPage, [
            'path' => '/items',
        ]);

        return (string) $paginator->links('ptah::components.forge-pagination')->toHtml();
    }

    #[Test]
    public function root_is_a_labelled_nav(): void
    {
        $html = $this->render(currentPage: 3);

        $this->assertMatchesRegularExpression('/<nav\b[^>]*aria-label="[^"]+"[^>]*class="ptah-pagination/', $html);
    }

    #[Test]
    public function middle_page_chevrons_carry_aria_label(): void
    {
        $html = $this->render(currentPage: 3);

        $this->assertStringContainsString('aria-label="Previous page"', $html);
        $this->assertStringContainsString('aria-label="Next page"', $html);
    }

    #[Test]
    public function first_page_disabled_prev_chevron_still_carries_aria_label(): void
    {
        $html = $this->render(currentPage: 1);

        // The disabled <span> chevron (no wire:click) must still be announced.
        $this->assertMatchesRegularExpression(
            '/<span class="p-2 rounded-md cursor-not-allowed ptah-c-pag_icon" aria-label="Previous page">/',
            $html
        );
    }

    // Estas duas casavam pelo `$set('page', N)` que os botoes usavam. O `$set`
    // era o bug do 500 (WithPagination nao tem propriedade publica `page`) e
    // saiu; casar pelo `gotoPage(N, ...)` mantem a asserticao sobre o que ela
    // realmente quer dizer — qual botao leva o aria-current — sem repetir a
    // forma exata da expressao, que PaginationClickTest ja vigia.

    #[Test]
    public function current_page_button_carries_aria_current(): void
    {
        $html = $this->render(currentPage: 3);

        $this->assertMatchesRegularExpression(
            '/wire:click="gotoPage\(3,[^"]*\)"\s+aria-current="page"/',
            $html
        );
    }

    #[Test]
    public function non_current_page_buttons_carry_no_aria_current(): void
    {
        $html = $this->render(currentPage: 3);

        // Sem a ancora do numero isto passaria a vazio: casa o botao 2 e exige
        // que o que vem depois dele NAO seja aria-current.
        $this->assertMatchesRegularExpression('/wire:click="gotoPage\(2,[^"]*\)"/', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/wire:click="gotoPage\(2,[^"]*\)"\s+aria-current="page"/',
            $html
        );
    }

    #[Test]
    public function disabled_chevron_no_longer_uses_the_failing_gray_300_utility(): void
    {
        $html = $this->render(currentPage: 1);

        $this->assertStringNotContainsString('text-gray-300', $html);
        $this->assertStringContainsString('ptah-c-pag_icon', $html);
    }
}
