<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Company;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Company;
use Ptah\Tests\TestCase;

/** 1.50.1: two companies with the same name broke on the slug unique constraint. */
class CompanySlugUniqueTest extends TestCase
{
    #[Test]
    public function a_repeated_name_gets_a_numbered_slug(): void
    {
        $a = Company::create(['name' => 'Engepeças Filial']);
        $b = Company::create(['name' => 'Engepeças Filial']);
        $b->delete();
        $c = Company::create(['name' => 'Engepeças Filial']);

        $this->assertSame('engepecas-filial', $a->slug);
        $this->assertSame('engepecas-filial-2', $b->slug);
        $this->assertSame('engepecas-filial-3', $c->slug, 'Um slug na lixeira tambem conta.');
    }
}
