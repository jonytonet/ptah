<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Stubs;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;

/**
 * Locks the fixes for 3 bugs in the `ptah-docker` publish tag's shipped
 * stubs (Dockerfile, docker-compose.yml, php.ini):
 *
 *   - BUG 1: `pecl install redis` failed the image build on
 *     php:8.3-fpm-alpine (`phpize' failed`) because the PECL toolchain
 *     ($PHPIZE_DEPS) was never installed before calling pecl.
 *   - BUG 2: every service had `container_name: ${APP_NAME:-ptah}_*`, and
 *     APP_NAME is free text (accepts spaces/accents), which Docker rejects
 *     as a container name outside [a-zA-Z0-9][a-zA-Z0-9_.-]*.
 *   - BUG 7: opcache.revalidate_freq was hardcoded to 0 (always
 *     revalidate), and PHP_OPCACHE_ENABLE was hardcoded to 0 in the
 *     compose file, disabling opcache outright — both are now
 *     env-configurable with sane defaults.
 *
 * HONEST LIMIT: this test does NOT build the Docker image or run
 * `docker compose up` — that requires a Docker daemon, unavailable to
 * PHPUnit. It only locks the shipped stub *content*.
 */
class DockerStubsTest extends TestCase
{
    #[Test]
    public function dockerfile_installs_and_cleans_up_the_pecl_build_toolchain_in_one_run_instruction(): void
    {
        $dockerfile = file_get_contents($this->dockerSourcePath('docker').'/php/Dockerfile');

        $this->assertStringContainsString('$PHPIZE_DEPS', $dockerfile);
        $this->assertStringContainsString('apk del .build-deps', $dockerfile);

        $runBlocks = $this->extractRunBlocks($dockerfile);

        $sameInstruction = false;
        foreach ($runBlocks as $block) {
            if (str_contains($block, '.build-deps') && str_contains($block, 'pecl install redis')) {
                $sameInstruction = true;
            }
        }

        $this->assertTrue(
            $sameInstruction,
            '`pecl install redis` and `.build-deps` must be installed/removed by the SAME RUN instruction.',
        );
    }

    #[Test]
    public function docker_compose_has_no_container_name_and_opcache_is_env_configurable(): void
    {
        $compose = file_get_contents($this->dockerSourcePath('docker-compose.yml'));

        $this->assertStringNotContainsString('container_name', $compose);
        $this->assertStringNotContainsString('PHP_OPCACHE_ENABLE: 0', $compose);
    }

    #[Test]
    public function php_ini_revalidates_opcache_on_an_env_configurable_interval_with_timestamps_validated(): void
    {
        $phpIni = file_get_contents($this->dockerSourcePath('docker').'/php/php.ini');

        $this->assertStringContainsString('${PHP_OPCACHE_REVALIDATE_FREQ:-2}', $phpIni);
        $this->assertMatchesRegularExpression('/opcache\.validate_timestamps\s*=\s*1\b/', $phpIni);
    }

    /**
     * Resolves a `ptah-docker` publish source path by its expected suffix
     * (e.g. "docker-compose.yml" or "docker" for the docker/ subtree).
     */
    private function dockerSourcePath(string $suffix): string
    {
        $paths = ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-docker');

        $this->assertNotEmpty($paths, 'ptah-docker publish tag must be registered');

        foreach (array_keys($paths) as $source) {
            $normalized = str_replace('\\', '/', $source);

            if (str_ends_with($normalized, '/'.$suffix) || $normalized === $suffix) {
                $resolved = realpath($source);
                $this->assertNotFalse($resolved, "Could not resolve ptah-docker source: {$source}");

                return str_replace('\\', '/', $resolved);
            }
        }

        $this->fail("No ptah-docker publish source ends with \"{$suffix}\".");
    }

    /**
     * Splits a Dockerfile into its `RUN` instruction blocks, joining
     * backslash line-continuations into a single block each. Intentionally
     * line-based (not a single multi-line regex spanning the whole file):
     * a naive `(?:\\\n.*)*` repeated-group regex is prone to catastrophic
     * backtracking that swallows unrelated instructions once a line without
     * a trailing backslash is reached.
     *
     * @return list<string>
     */
    private function extractRunBlocks(string $dockerfile): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $dockerfile);

        $blocks = [];
        $buffer = '';
        $inRun = false;

        foreach ($lines as $line) {
            if (! $inRun && preg_match('/^RUN\b/', $line) === 1) {
                $inRun = true;
                $buffer = '';
            }

            if ($inRun) {
                $buffer .= $line."\n";

                if (preg_match('/\\\\\s*$/', $line) !== 1) {
                    $blocks[] = $buffer;
                    $inRun = false;
                }
            }
        }

        return $blocks;
    }
}
