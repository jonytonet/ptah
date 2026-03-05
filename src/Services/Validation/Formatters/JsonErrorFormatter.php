<?php

declare(strict_types=1);

namespace Ptah\Services\Validation\Formatters;

use Ptah\Exceptions\PtahException;
use Illuminate\Support\Str;

/**
 * Formats exceptions for JSON API responses.
 *
 * Follows RFC 7807 Problem Details for HTTP APIs.
 *
 * @package Ptah\Services\Validation\Formatters
 * @see https://tools.ietf.org/html/rfc7807
 */
class JsonErrorFormatter
{
    /**
     * Format an exception for JSON API response.
     *
     * @param PtahException $exception
     * @param int|null $httpStatus
     * @return array<string, mixed>
     */
    public function format(PtahException $exception, ?int $httpStatus = null): array
    {
        if (method_exists($exception, 'formatAsJsonResponse')) {
            return $exception->formatAsJsonResponse();
        }

        return $this->defaultFormat($exception, $httpStatus);
    }

    /**
     * Default formatting following RFC 7807.
     *
     * @param PtahException $exception
     * @param int|null $httpStatus
     * @return array<string, mixed>
     */
    protected function defaultFormat(PtahException $exception, ?int $httpStatus = null): array
    {
        return [
            'type' => $this->getErrorType($exception),
            'title' => $this->getErrorTitle($exception),
            'detail' => $exception->getMessage(),
            'status' => $httpStatus ?? $this->getDefaultHttpStatus($exception),
            'context' => $exception->getContext(),
            'trace_id' => $this->generateTraceId(),
        ];
    }

    /**
     * Format exception with additional metadata.
     *
     * @param PtahException $exception
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function formatWithMetadata(PtahException $exception, array $metadata = []): array
    {
        $base = $this->format($exception);

        return array_merge($base, [
            'meta' => $metadata,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Format multiple exceptions as a validation error response.
     *
     * @param array<int, PtahException> $exceptions
     * @return array<string, mixed>
     */
    public function formatMultiple(array $exceptions): array
    {
        return [
            'type' => 'https://ptah.dev/errors/validation-failed',
            'title' => 'Validation Failed',
            'detail' => 'Multiple validation errors occurred',
            'status' => 422,
            'errors' => array_map(fn($e) => $this->format($e), $exceptions),
            'trace_id' => $this->generateTraceId(),
        ];
    }

    /**
     * Format as Laravel validation error format.
     *
     * Compatible with Laravel's ValidationException format.
     *
     * @param PtahException $exception
     * @return array<string, mixed>
     */
    public function formatAsLaravelValidation(PtahException $exception): array
    {
        $field = $exception->getContext()['field'] ?? 'general';
        $message = $exception->getMessage();

        return [
            'message' => 'The given data was invalid.',
            'errors' => [
                $field => [$message],
            ],
        ];
    }

    /**
     * Format for logging (includes stack trace).
     *
     * @param PtahException $exception
     * @return array<string, mixed>
     */
    public function formatForLogging(PtahException $exception): array
    {
        return [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'context' => $exception->getContext(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get error type URL identifier.
     *
     * @param PtahException $exception
     * @return string
     */
    protected function getErrorType(PtahException $exception): string
    {
        $className = class_basename($exception);

        return 'https://ptah.dev/errors/' . Str::kebab($className);
    }

    /**
     * Get human-readable error title.
     *
     * @param PtahException $exception
     * @return string
     */
    protected function getErrorTitle(PtahException $exception): string
    {
        $className = class_basename($exception);

        return Str::headline($className);
    }

    /**
     * Get default HTTP status code based on exception type.
     *
     * @param PtahException $exception
     * @return int
     */
    protected function getDefaultHttpStatus(PtahException $exception): int
    {
        $className = class_basename($exception);

        return match (true) {
            str_contains($className, 'Validation') => 422, // Unprocessable Entity
            str_contains($className, 'NotFound') => 404,   // Not Found
            str_contains($className, 'Unauthorized') => 401, // Unauthorized
            str_contains($className, 'Forbidden') => 403,   // Forbidden
            str_contains($className, 'Generation') => 500,  // Internal Server Error
            default => 400, // Bad Request
        };
    }

    /**
     * Generate a unique trace ID for error tracking.
     *
     * @return string
     */
    protected function generateTraceId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Convert formatted error to JSON string.
     *
     * @param PtahException $exception
     * @param int $options
     * @return string
     */
    public function toJson(PtahException $exception, int $options = JSON_UNESCAPED_SLASHES): string
    {
        return json_encode(
            $this->format($exception),
            $options | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Create a JSON response for Laravel.
     *
     * @param PtahException $exception
     * @param int|null $httpStatus
     * @return \Illuminate\Http\JsonResponse
     */
    public function toResponse(PtahException $exception, ?int $httpStatus = null): \Illuminate\Http\JsonResponse
    {
        $data = $this->format($exception, $httpStatus);
        $status = $data['status'] ?? 400;

        return response()->json($data, $status);
    }
}
