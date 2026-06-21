<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ScheduledAlert;
use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\ScheduledAlertRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Sends the obligation review reminders — the core of the system's value now that there is no
 * consultant: making sure no review slips past the audit.
 *
 * For each due alert it resolves the recipients (the active users holding the alert's roles, plus
 * Direction when the alert is past its escalation window), groups every person's due obligations
 * into a single e-mail (so nobody gets one mail per document), sends it, and stamps the alerts as
 * notified so they are not re-sent within the same cycle.
 */
final class ObligationAlertNotifier
{
    /** Stable code of the role that receives escalated alerts. */
    private const string ESCALATION_ROLE = 'direction';

    public function __construct(
        private readonly ScheduledAlertRepository $alerts,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.mailer_from%')]
        private readonly string $from,
    ) {
    }

    /**
     * Sends the reminders due on the given date and returns a summary.
     *
     * @param \DateTimeImmutable $on reference date (today)
     *
     * @return array{alerts: int, emails: int, withoutRecipient: int} how many alerts were due, how
     *                                                                 many e-mails were sent and how
     *                                                                 many due alerts had no recipient
     */
    public function notify(\DateTimeImmutable $on): array
    {
        $due = array_filter(
            $this->alerts->findDueCandidates($on),
            static fn (ScheduledAlert $alert) => $alert->needsNotification($on),
        );

        $escalationRole = $this->roles->findOneBy(['code' => self::ESCALATION_ROLE]);

        // Group due alerts per recipient user so each person gets a single digest e-mail.
        /** @var array<int, array{user: User, alerts: ScheduledAlert[]}> $perUser */
        $perUser = [];
        $notified = [];
        $withoutRecipient = 0;

        foreach ($due as $alert) {
            $roles = $alert->getRecipientRoles()->toArray();
            if (null !== $escalationRole && $alert->shouldEscalate($on) && !\in_array($escalationRole, $roles, true)) {
                $roles[] = $escalationRole;
            }

            $recipients = [];
            foreach ($roles as $role) {
                foreach ($this->users->findActiveByRole($role) as $user) {
                    $recipients[(int) $user->getId()] = $user;
                }
            }

            if ([] === $recipients) {
                ++$withoutRecipient;

                continue;
            }

            foreach ($recipients as $id => $user) {
                $perUser[$id] ??= ['user' => $user, 'alerts' => []];
                $perUser[$id]['alerts'][] = $alert;
            }
            $notified[] = $alert;
        }

        foreach ($perUser as $entry) {
            $this->send($entry['user'], $entry['alerts'], $on);
        }

        // Stamp only the alerts that reached at least one recipient, so those without one are
        // retried on the next run (e.g. once a user is assigned the role).
        foreach ($notified as $alert) {
            $alert->setLastNotifiedAt($on);
        }
        $this->em->flush();

        return ['alerts' => \count($due), 'emails' => \count($perUser), 'withoutRecipient' => $withoutRecipient];
    }

    /**
     * Renders and sends one digest e-mail to a user with their due obligations.
     *
     * @param ScheduledAlert[] $alerts the user's due alerts
     */
    private function send(User $user, array $alerts, \DateTimeImmutable $on): void
    {
        $html = $this->twig->render('email/obligation_alert.html.twig', [
            'user' => $user,
            'alerts' => $alerts,
            'today' => $on,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($user->getEmail())
            ->subject(sprintf('Revisiones del SGA pendientes (%d)', \count($alerts)))
            ->html($html);

        $this->mailer->send($email);
    }
}
