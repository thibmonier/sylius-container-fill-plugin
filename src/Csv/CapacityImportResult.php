<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Csv;

final readonly class CapacityImportResult
{
    /** @param array<int, string> $errors message by line number */
    public function __construct(
        public int $imported,
        public int $removed,
        public array $errors,
        public bool $dryRun,
        /** False for a dry run and when a line is rejected: the import is all or nothing. */
        public bool $written = false,
    ) {
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
