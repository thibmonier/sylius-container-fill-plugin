<?php

declare(strict_types=1);

namespace Tmonier\SyliusContainerFillPlugin\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvFormat;
use Tmonier\SyliusContainerFillPlugin\Csv\CapacityCsvImporter;

#[AsCommand(name: 'tmonier:container-fill:import-capacities', description: 'Import variant capacities per container type from a CSV file')]
final class ImportCapacitiesCommand extends Command
{
    public function __construct(private readonly CapacityCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'CSV file (columns: ' . implode(';', CapacityCsvFormat::COLUMNS) . ')')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check the file without writing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');
        $contents = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            $io->error(sprintf('Cannot read "%s".', $file));

            return Command::FAILURE;
        }

        $result = $this->importer->import($contents, (bool) $input->getOption('dry-run'));
        foreach ($result->errors as $line => $error) {
            $io->writeln(sprintf('<comment>Line %d:</comment> %s', $line, $error));
        }
        $io->success(sprintf('%s%d capacities imported, %d removed, %d lines rejected.', $result->dryRun ? '[dry run] ' : '', $result->imported, $result->removed, \count($result->errors)));

        return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }
}
