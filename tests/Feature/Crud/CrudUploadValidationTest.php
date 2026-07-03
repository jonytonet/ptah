<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Tests\TestCase;

/**
 * Image uploads are validated by their REAL mime type (the client extension is
 * spoofable). An image column must receive an actual raster image and never an
 * SVG (scriptable → stored XSS once served from the public disk).
 */
class CrudUploadValidationTest extends TestCase
{
    /** @return array<string,string> field => error */
    private function validate(UploadedFile $file): array
    {
        $crud = new BaseCrud;
        $col = ['colsNomeFisico' => 'photo', 'colsTipo' => 'image'];
        $crud->crudConfig = ['cols' => [$col]];
        $crud->imageUploads = ['photo' => $file];

        $m = new \ReflectionMethod($crud, 'validateImageUploads');
        $m->setAccessible(true);

        return $m->invoke($crud, [$col]);
    }

    #[Test]
    public function accepts_a_real_image(): void
    {
        $errors = $this->validate(UploadedFile::fake()->image('avatar.png'));

        $this->assertArrayNotHasKey('photo', $errors);
    }

    #[Test]
    public function rejects_an_svg_even_with_an_image_extension(): void
    {
        $svg = UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml');

        $this->assertArrayHasKey('photo', $this->validate($svg));
    }

    #[Test]
    public function rejects_a_non_image_disguised_as_png(): void
    {
        // Real content is HTML/text; only the name says .png.
        $fake = UploadedFile::fake()->create('x.png', 4, 'text/html');

        $this->assertArrayHasKey('photo', $this->validate($fake));
    }
}
