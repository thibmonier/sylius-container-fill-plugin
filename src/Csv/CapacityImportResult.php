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
    ) {
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
