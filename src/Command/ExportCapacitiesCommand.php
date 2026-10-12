<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvExporter;

#[AsCommand(name: 'tmonier:container-fill:export-capacities', description: 'Export variant capacities per container type as CSV (import format)')]
final class ExportCapacitiesCommand extends Command
{
    public function __construct(private readonly CapacityCsvExporter $exporter)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write($this->exporter->export(), false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
