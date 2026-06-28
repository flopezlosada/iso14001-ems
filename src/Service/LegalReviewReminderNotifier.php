<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\LegalRequirementRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * E-mails a reminder when a legal requirement's compliance review (PC-06.03) is due, so the upcoming
 * inspection/evaluation is not missed if nobody happens to open the module. The recipients are the
 * SGA manager(s), who own the legal register.
 *
 * Mirrors {@see AspectTrendNotifier}: there is no per-item "already notified" state, so this is a
 * periodic digest whose cadence is set by how often the command is scheduled (run it WEEKLY, not
 * daily, so a pending review nudges weekly instead of every day). It only sends when at least one
 * review is due.
 */
final class LegalReviewReminderNotifier
{
    /** Stable code of the role that owns the legal register and receives these reminders. */
    private const string MANAGER_ROLE = 'ems_manager';

    public function __construct(
        private readonly LegalRequirementRepository $requirements,
        private readonly RoleRepository $roles,
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        #[Autowire('%app.mailer_from%')]
        private readonly string $from,
    ) {
    }

    /**
     * Sends the review reminder digest as of the given date and returns a summary.
     *
     * @param \DateTimeImmutable $on the reference date (today)
     *
     * @return array{due: int, emails: int} how many reviews are due and how many e-mails were sent
     */
    public function notify(\DateTimeImmutable $on): array
    {
        $due = $this->requirements->findDueForReview($on);
        if ([] === $due) {
            return ['due' => 0, 'emails' => 0];
        }

        $role = $this->roles->findOneBy(['code' => self::MANAGER_ROLE]);
        $recipients = null !== $role ? $this->users->findActiveByRole($role) : [];

        $sent = 0;
        foreach ($recipients as $user) {
            $this->send($user, $due, $on);
            ++$sent;
        }

        return ['due' => \count($due), 'emails' => $sent];
    }

    /**
     * Renders and sends one digest e-mail listing the requirements whose review is due.
     *
     * @param \App\Entity\LegalRequirement[] $due the requirements needing review
     */
    private function send(User $user, array $due, \DateTimeImmutable $on): void
    {
        $html = $this->twig->render('email/legal_review_reminder.html.twig', [
            'user' => $user,
            'requirements' => $due,
            'today' => $on,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($user->getEmail())
            ->subject(sprintf('Revisiones de requisitos legales pendientes (%d)', \count($due)))
            ->html($html);

        $this->mailer->send($email);
    }
}
