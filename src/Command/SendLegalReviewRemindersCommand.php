<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LegalReviewReminderNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sends the legal-requirement review reminders: requirements whose compliance review (PC-06.03) is
 * overdue or approaching. Meant to run periodically from cron:
 *
 *   php bin/console app:legal:send-review-reminders
 *
 * Same hosting caveat as the obligation reminders: needs an SSH/CLI cron or a thin authenticated
 * HTTP entry point where only HTTP cron is available.
 */
#[AsCommand(
    name: 'app:legal:send-review-reminders',
    description: 'Envía por e-mail los requisitos legales cuya revisión está vencida o próxima. Programar con cadencia BAJA (semanal): reenvía mientras la revisión siga pendiente (no guarda estado de "ya avisado").',
)]
final class SendLegalReviewRemindersCommand extends Command
{
    public function __construct(private readonly LegalReviewReminderNotifier $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $summary = $this->notifier->notify($today);

        // Reviews due but nobody to notify (no active user holds the manager role) is a real
        // operational gap, not a success: make it visible instead of a silent "0 sent".
        if ($summary['due'] > 0 && 0 === $summary['emails']) {
            $io->warning(sprintf(
                '%d revisión(es) pendiente(s), pero ningún usuario activo tiene el rol gestor: no se ha enviado ningún correo.',
                $summary['due'],
            ));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%d revisión(es) pendiente(s): %d correo(s) enviado(s).',
            $summary['due'],
            $summary['emails'],
        ));

        return Command::SUCCESS;
    }
}
