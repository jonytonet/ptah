<?php

declare(strict_types=1);

namespace Ptah\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Where does a model with this short name actually live?
 *
 * `ptah:forge` generates `belongsTo(Category::class)` for a `category_id` field,
 * and the `use` for `Category` used to be left as a TODO for every foreign key
 * of every entity — deliberately, because guessing the namespace wrong (the
 * related model sitting in another sub-folder) would produce an import that
 * looks right and fails at runtime. The package's own skill then made fixing
 * those TODOs a MANDATORY step after every scaffold: open each generated model,
 * find where each related class lives, write the import. For an agent that is
 * a file read and an edit per foreign key, repeated for the whole module.
 *
 * This keeps the original rule — never guess — and removes the guesswork where
 * there is none: when exactly ONE class of that name exists under the models
 * directory, that is the answer. Zero (not generated yet) or more than one
 * (genuinely ambiguous) and the caller keeps the TODO, now saying which.
 *
 * The namespace is read from the file's own `namespace` declaration rather
 * than derived from its path, because not every host follows PSR-4 to the
 * letter, and an import built from the path would be exactly the plausible,
 * wrong answer the TODO existed to prevent.
 */
final class ModelLocator
{
    /**
     * Fully qualified names of every class called `$shortName` under
     * `$modelsPath`, sorted.
     *
     * @return list<string>
     */
    public static function find(string $shortName, string $modelsPath): array
    {
        if ($shortName === '' || ! is_dir($modelsPath)) {
            return [];
        }

        $found = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($modelsPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getFilename() !== $shortName.'.php') {
                continue;
            }

            $namespace = self::namespaceOf($file->getPathname());

            if ($namespace === null) {
                continue;
            }

            $found[] = $namespace.'\\'.$shortName;
        }

        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /**
     * The `namespace` a PHP file declares, or null if it declares none.
     */
    private static function namespaceOf(string $path): ?string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            // A declaracao vem no topo; nao ha motivo para ler o arquivo todo.
            for ($i = 0; $i < 60 && ($line = fgets($handle)) !== false; $i++) {
                if (preg_match('/^\s*namespace\s+([A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)\s*;/', $line, $m) === 1) {
                    return $m[1];
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }
}
