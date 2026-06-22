<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AspectTrendNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the "aspectos a vigilar" digest: consumption/waste aspects already trending worse than the
 * threshold. Meant to run periodically from cron (a lower cadence than the obligation reminders, as
 * a trend changes slowly):
 *
 *   php bin/console app:aspects:send-trend-alerts
 *
 * Same hosting caveat as the obligation reminders: needs an SSH/CLI cron or a thin authenticated
 * HTTP entry point where only HTTP cron is available.
 */
#[AsCommand(
    name: 'app:aspects:send-trend-alerts',
    description: 'Envía por e-mail los aspectos cuyo consumo/residuos van al alza. Programar con cadencia BAJA (semanal/mensual): reenvía mientras la tendencia persista (no guarda estado de "ya avisado").',
)]
final class SendAspectTrendAlertsCommand extends Command
{
    public function __construct(private readonly AspectTrendNotifier $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $summary = $this->notifier->notify($today);

        // Trends detected but nobody to notify (no active user holds the manager role) is a real
        // operational gap, not a success: make it visible instead of a silent "0 sent".
        if ($summary['watched'] > 0 && 0 === $summary['emails']) {
            $io->warning(sprintf(
                '%d aspecto(s) a vigilar, pero ningún usuario activo tiene el rol gestor: no se ha enviado ningún correo.',
                $summary['watched'],
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%d aspecto(s) a vigilar: %d correo(s) enviado(s).',
            $summary['watched'],
            $summary['emails'],
        ));

        return Command::SUCCESS;
    }
}
