<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AutomaticNonConformityGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Opens the non-conformities the enabled settings call for (breached indicators, unmet objectives).
 * This is the canonical way to run the engine from an SSH/CLI cron:
 *
 *   php bin/console app:nonconformities:auto-generate
 *
 * On hosting whose only scheduler is an HTTP cron, the same job is reachable over HTTP via
 * {@see \App\Controller\AutomaticNonConformityCronController}; both delegate to the same
 * {@see AutomaticNonConformityGenerator}. The job is idempotent, so running it repeatedly is safe.
 */
#[AsCommand(
    name: 'app:nonconformities:auto-generate',
    description: 'Abre automáticamente las no conformidades según las reglas activadas en los ajustes.',
)]
final class GenerateAutomaticNonConformitiesCommand extends Command
{
    public function __construct(private readonly AutomaticNonConformityGenerator $generator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $summary = $this->generator->generate(new \DateTimeImmutable('today'));

        $io->success(sprintf(
            '%d fuente(s) candidata(s): %d no conformidad(es) abierta(s).',
            $summary['candidates'],
            $summary['created'],
        ));

        return Command::SUCCESS;
    }
}
