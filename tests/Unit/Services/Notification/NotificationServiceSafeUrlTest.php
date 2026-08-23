<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Notification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Services\Notification\NotificationService;

/**
 * NotificationService::safeUrl() neutralises dangerous URL schemes at
 * render/redirect time — same regex as HasCrudRenderers::renderLink()
 * (`^\s*(javascript|data|vbscript):`).
 */
class NotificationServiceSafeUrlTest extends TestCase
{
    #[Test]
    public function null_and_empty_are_null(): void
    {
        $this->assertNull(NotificationService::safeUrl(null));
        $this->assertNull(NotificationService::safeUrl(''));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dangerousProvider(): array
    {
        return [
            'javascript:' => ['javascript:alert(1)'],
            'JavAScRipT: mixed case' => ['JavAScRipT:alert(1)'],
            'leading whitespace + javascript:' => ['   javascript:alert(1)'],
            'data:' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript:' => ['vbscript:msgbox(1)'],
        ];
    }

    #[Test]
    #[DataProvider('dangerousProvider')]
    public function dangerous_schemes_are_neutralised(string $url): void
    {
        $this->assertNull(NotificationService::safeUrl($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function safeProvider(): array
    {
        return [
            'relative path' => ['/pets/42'],
            'absolute https url' => ['https://app.example.com/pets/42'],
            'absolute http url' => ['http://app.example.com/pets/42'],
            'mailto is left alone (not in the deny-list)' => ['mailto:someone@example.com'],
        ];
    }

    #[Test]
    #[DataProvider('safeProvider')]
    public function safe_urls_pass_through_unchanged(string $url): void
    {
        $this->assertSame($url, NotificationService::safeUrl($url));
    }
}
