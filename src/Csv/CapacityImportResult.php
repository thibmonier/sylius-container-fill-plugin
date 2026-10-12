<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Csv;

final class CapacityImportResult
{
    /** @param array<int, string> $errors message by line number */
    public function __construct(
        public readonly int $imported,
        public readonly int $removed,
        public readonly array $errors,
        public readonly bool $dryRun,
        /** False for a dry run and when a line is rejected: the import is all or nothing. */
        public readonly bool $written = false,
    ) {
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
