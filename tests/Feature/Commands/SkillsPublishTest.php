<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;

/**
 * The `ptah-skills` publish tag ships the agent skills into the app's
 * .claude/skills (Boost does NOT auto-discover third-party package skills, so
 * this is what actually makes them available). Verifies the tag is wired and the
 * package carries the three skills.
 */
class SkillsPublishTest extends TestCase
{
    #[Test]
    public function ptah_skills_tag_maps_the_package_skills_to_dot_claude_skills(): void
    {
        $paths = ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-skills');

        $this->assertNotEmpty($paths, 'ptah-skills publish tag must be registered');

        $source = array_key_first($paths);
        $dest = $paths[$source];

        $this->assertDirectoryExists($source);
        // Normalise separators — base_path('.claude/skills') keeps forward slashes.
        $normalizedDest = str_replace('\\', '/', rtrim($dest, '/\\'));
        $this->assertStringEndsWith('.claude/skills', $normalizedDest);

        foreach (['ptah-development', 'ptah-scaffold', 'ptah-data-layer'] as $skill) {
            $this->assertFileExists(
                $source.DIRECTORY_SEPARATOR.$skill.DIRECTORY_SEPARATOR.'SKILL.md',
                "Package must ship the {$skill} skill",
            );
        }
    }

    #[Test]
    public function shipped_skills_have_valid_frontmatter(): void
    {
        $source = array_key_first(
            ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-skills')
        );

        foreach (['ptah-development', 'ptah-scaffold', 'ptah-data-layer'] as $skill) {
            $content = file_get_contents($source.DIRECTORY_SEPARATOR.$skill.DIRECTORY_SEPARATOR.'SKILL.md');

            $this->assertStringStartsWith('---', $content, "{$skill} must open with YAML frontmatter");
            $this->assertStringContainsString('name: '.$skill, $content, "{$skill} frontmatter name must match its folder");
            $this->assertStringContainsString('description:', $content);
        }
    }
}
