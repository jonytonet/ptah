<?php

declare(strict_types=1);

namespace Ptah\Generators;

/**
 * Value object imutável que representa o resultado de uma geração.
 */
readonly class GeneratorResult
{
    public const DONE    = 'DONE';
    public const SKIPPED = 'SKIPPED';
    public const ERROR   = 'ERROR';

    public function __construct(
        public string  $label,
        public string  $status,
        public ?string $path    = null,
        public ?string $message = null,
    ) {}

    public static function done(string $label, string $path): self
    {
        return new self($label, self::DONE, $path);
    }

    public static function skipped(string $label, string $path): self
    {
        return new self($label, self::SKIPPED, $path);
    }

    public static function error(string $label, string $path, string $message): self
    {
        return new self($label, self::ERROR, $path, $message);
    }

    public function isDone(): bool
    {
        return $this->status === self::DONE;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::SKIPPED;
    }

    public function isError(): bool
    {
        return $this->status === self::ERROR;
    }

    /**
     * Retorna o status formatado para exibição no terminal.
     */
    public function formattedStatus(): string
    {
        return match ($this->status) {
            self::DONE    => '<fg=green;options=bold>DONE</>',
            self::SKIPPED => '<fg=yellow;options=bold>SKIPPED</>',
            self::ERROR   => '<fg=red;options=bold>ERROR</>',
            default       => $this->status,
        };
    }
}
