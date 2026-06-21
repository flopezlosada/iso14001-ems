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
 * Sends the obligation review reminders that are due today. Meant to run daily from cron:
 *
 *   php bin/console app:obligations:send-alerts
 *
 * On hosting where only HTTP cron is available (e.g. IONOS), this still needs an SSH/CLI cron or a
 * thin authenticated HTTP entry point that invokes it — to be decided when the hosting is settled.
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
