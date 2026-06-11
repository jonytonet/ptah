<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\RequestGenerator;

/**
 * Covers the generated FormRequests: Store rules use required/nullable,
 * Update rules add `sometimes`, and API mode produces the API pair.
 */
class RequestGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function store_request_has_required_and_nullable_rules(): void
    {
        $result = (new RequestGenerator($this->files))->generateStore($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('class StoreWidgetRequest', $content);
        $this->assertStringContainsString("'name' => 'required|string|max:255',", $content);
        // nullable decimal → nullable|numeric
        $this->assertStringContainsString("'price' => 'nullable|numeric',", $content);
        $this->assertStringContainsString("'category_id' => 'required|integer',", $content);
    }

    #[Test]
    public function update_request_prefixes_rules_with_sometimes(): void
    {
        $result = (new RequestGenerator($this->files))->generateUpdate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('class UpdateWidgetRequest', $content);
        $this->assertStringContainsString("'name' => 'sometimes|required|string|max:255',", $content);
        $this->assertStringContainsString("'category_id' => 'sometimes|required|integer',", $content);
    }

    #[Test]
    public function api_mode_generates_the_api_request_pair(): void
    {
        $context = $this->context(withApi: true, withViews: false);
        $generator = new RequestGenerator($this->files);

        $create = $generator->generateCreateApi($context);
        $update = $generator->generateUpdateApi($context);

        $this->assertTrue($create->isDone(), $create->message ?? '');
        $this->assertTrue($update->isDone(), $update->message ?? '');

        $this->assertStringContainsString(
            'class CreateWidgetApiRequest',
            (string) file_get_contents($create->path),
        );
        $this->assertStringContainsString(
            'class UpdateWidgetApiRequest',
            (string) file_get_contents($update->path),
        );

        // API requests live under Http/Requests/API
        $this->assertStringContainsString('/API/', str_replace('\\', '/', $create->path));
    }
}
