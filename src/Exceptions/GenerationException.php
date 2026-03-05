<?php

declare(strict_types=1);

namespace Ptah\Exceptions;

use Ptah\Exceptions\Concerns\FormatsError;

/**
 * Exception thrown when code generation fails.
 *
 * This exception is thrown during scaffolding when the forge command
 * encounters errors generating models, repositories, services, or other files.
 *
 * @package Ptah\Exceptions
 */
class GenerationException extends PtahException
{
    use FormatsError;

    /**
     * Create a new GenerationException for file already exists.
     *
     * @param string $filePath
     * @param bool $canForce
     * @return static
     */
    public static function fileAlreadyExists(string $filePath, bool $canForce = false): static
    {
        $message = sprintf('File already exists: %s', $filePath);

        $context = [
            'file_path' => $filePath,
        ];

        if ($canForce) {
            $context['suggestion'] = 'Use --force flag to overwrite existing file';
        }

        return static::withContext($message, $context);
    }

    /**
     * Create a new GenerationException for stub not found.
     *
     * @param string $stubName
     * @param array<int, string> $searchedPaths
     * @return static
     */
    public static function stubNotFound(string $stubName, array $searchedPaths): static
    {
        $message = sprintf('Stub file "%s" not found', $stubName);

        return static::withContext($message, [
            'stub_name' => $stubName,
            'searched_paths' => $searchedPaths,
        ]);
    }

    /**
     * Create a new GenerationException for invalid template.
     *
     * @param string $template
     * @param string $error
     * @return static
     */
    public static function invalidTemplate(string $template, string $error): static
    {
        $message = sprintf('Invalid template "%s": %s', $template, $error);

        return static::withContext($message, [
            'template' => $template,
            'error' => $error,
        ]);
    }

    /**
     * Create a new GenerationException for failed write operation.
     *
     * @param string $filePath
     * @param string $error
     * @return static
     */
    public static function failedToWrite(string $filePath, string $error): static
    {
        $message = sprintf('Failed to write file "%s": %s', $filePath, $error);

        return static::withContext($message, [
            'file_path' => $filePath,
            'error' => $error,
        ]);
    }

    /**
     * Create a new GenerationException for directory creation failure.
     *
     * @param string $directory
     * @param string $error
     * @return static
     */
    public static function failedToCreateDirectory(string $directory, string $error): static
    {
        $message = sprintf('Failed to create directory "%s": %s', $directory, $error);

        return static::withContext($message, [
            'directory' => $directory,
            'error' => $error,
        ]);
    }

    /**
     * Create a new GenerationException for invalid field definition.
     *
     * @param string $field
     * @param string $error
     * @return static
     */
    public static function invalidFieldDefinition(string $field, string $error): static
    {
        $message = sprintf('Invalid field definition "%s": %s', $field, $error);

        return static::withContext($message, [
            'field' => $field,
            'error' => $error,
            'suggestion' => 'Use format: field:type[:length][:attributes]',
        ]);
    }

    /**
     * Get HTTP status code for API responses.
     *
     * @return int
     */
    protected function getHttpStatusCode(): int
    {
        return 500; // Internal Server Error
    }
}
