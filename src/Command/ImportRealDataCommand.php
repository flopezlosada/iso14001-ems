<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Import\CsvReader;
use App\Service\Import\DatasetImporter;
use App\Service\Import\ImportReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Imports the real ISO 14001 historical data (3 years of records) from the normalized CSVs into
 * the database. Source of truth for the clean CSVs is the offline ETL under tools/etl/.
 *
 *   php bin/console app:import-real-data consumptions          # one dataset
 *   php bin/console app:import-real-data all --dry-run         # everything, no writes
 *
 * Idempotent: safe to re-run (upsert by natural key). Rows that fail validation are written to a
 * "<dataset>.rejected.csv" quarantine file next to the source and never persisted.
 */
#[AsCommand(
    name: 'app:import-real-data',
    description: 'Importa los datos reales (CSV normalizados) a la base de datos, de forma idempotente.',
)]
final class ImportRealDataCommand extends Command
{
    /**
     * @param iterable<DatasetImporter> $importers all registered dataset importers
     */
    public function __construct(
        #[AutowireIterator('app.dataset_importer')]
        private readonly iterable $importers,
        private readonly CsvReader $csvReader,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'dataset',
                InputArgument::REQUIRED,
                'Dataset a importar (clave del importer) o "all" para todos.',
            )
            ->addOption(
                'dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directorio de los CSV normalizados.',
                'fixtures/real',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Procesa y valida sin escribir nada en la base de datos.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dataset = (string) $input->getArgument('dataset');
        $dryRun = (bool) $input->getOption('dry-run');
        $dir = $this->resolveDir((string) $input->getOption('dir'));

        $all = iterator_to_array($this->importers, false);
        $importers = $this->selectImporters($dataset, $all);
        if ([] === $importers) {
            $keys = array_map(static fn (DatasetImporter $i) => $i->key(), $all);
            $io->error(sprintf('Dataset desconocido: "%s". Disponibles: %s, all.', $dataset, implode(', ', $keys)));

            return Command::INVALID;
        }

        if ($dryRun) {
            $io->note('Modo dry-run: no se escribirá nada en la base de datos.');
        }

        $hadError = false;
        foreach ($importers as $importer) {
            $path = $dir.\DIRECTORY_SEPARATOR.$importer->csvFilename();
            if (!is_file($path)) {
                $io->warning(sprintf('[%s] No se encontró el CSV "%s". Se omite.', $importer->key(), $path));
                $hadError = true;
                continue;
            }

            $io->section(sprintf('Importando "%s" desde %s', $importer->key(), $path));
            $report = $importer->import($this->csvReader->read($path), $dryRun);
            $this->renderReport($io, $report);

            if ([] !== $report->getRejected()) {
                $quarantine = $this->writeQuarantine($dir, $importer->key(), $report);
                $io->warning(sprintf('%d fila(s) rechazada(s) escritas en %s para revisión manual.', \count($report->getRejected()), $quarantine));
                $hadError = true;
            }
        }

        if ($hadError) {
            $io->warning('Importación terminada con incidencias (CSV faltantes o filas rechazadas). Revisa los avisos.');

            return Command::FAILURE;
        }

        $io->success('Importación completada sin incidencias.');

        return Command::SUCCESS;
    }

    /**
     * Resolves the data directory to an absolute path, anchoring relative paths to the project dir.
     */
    private function resolveDir(string $dir): string
    {
        if (str_starts_with($dir, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
            return rtrim($dir, '/\\');
        }

        return $this->projectDir.\DIRECTORY_SEPARATOR.rtrim($dir, '/\\');
    }

    /**
     * Selects the importers to run for the given dataset key, or all of them for "all".
     *
     * @param list<DatasetImporter> $all every registered importer, already materialized
     *
     * @return list<DatasetImporter>
     */
    private function selectImporters(string $dataset, array $all): array
    {
        if ('all' === $dataset) {
            return $all;
        }

        return array_values(array_filter($all, static fn (DatasetImporter $i) => $i->key() === $dataset));
    }

    private function renderReport(SymfonyStyle $io, ImportReport $report): void
    {
        $io->definitionList(
            ['Creados' => (string) $report->getCreated()],
            ['Actualizados' => (string) $report->getUpdated()],
            ['Rechazados' => (string) \count($report->getRejected())],
        );
    }

    /**
     * Writes the rejected rows to a quarantine CSV next to the source, preserving the original
     * columns plus the rejection reason. Nothing is dropped silently.
     *
     * @return string the path of the quarantine file written
     */
    private function writeQuarantine(string $dir, string $key, ImportReport $report): string
    {
        $path = $dir.\DIRECTORY_SEPARATOR.$key.'.rejected.csv';
        $rejected = $report->getRejected();
        $columns = array_keys($rejected[0]['data']);

        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new \RuntimeException(sprintf('No se pudo escribir el fichero de cuarentena "%s".', $path));
        }

        try {
            fputcsv($handle, [...$columns, '_line', '_reason']);
            foreach ($rejected as $entry) {
                fputcsv($handle, [...array_values($entry['data']), $entry['line'], $entry['reason']]);
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }
}
