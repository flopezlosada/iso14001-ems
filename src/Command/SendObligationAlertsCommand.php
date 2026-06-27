<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ObligationAlertNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the obligation review reminders that are due today. This is the canonical way to run the
 * engine from an SSH/CLI cron:
 *
 *   php bin/console app:obligations:send-alerts
 *
 * On hosting whose only scheduler is an HTTP cron (cdmon/IONOS cannot run a shell command), the same
 * job is reachable over HTTP via {@see \App\Controller\ObligationAlertCronController}; both delegate
 * to the same {@see ObligationAlertNotifier}.
 */
#[AsCommand(
    name: 'app:obligations:send-alerts',
    description: 'Envía por e-mail los recordatorios de revisión de obligaciones vencidas hoy.',
)]
final class SendObligationAlertsCommand extends Command
{
    public function __construct(private readonly ObligationAlertNotifier $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $summary = $this->notifier->notify($today);

        $io->success(sprintf(
            '%d obligación(es) vencida(s): %d correo(s) enviado(s), %d sin destinatario asignado.',
            $summary['alerts'],
            $summary['emails'],
            $summary['withoutRecipient'],
        ));

        return Command::SUCCESS;
    }
}
