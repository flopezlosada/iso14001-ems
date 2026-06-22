<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\EnvironmentalAspectRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Pushes the "aspectos a vigilar" by e-mail: the consumption/waste aspects whose data is already
 * trending worse than the threshold ({@see AspectIntensityEstimator::watchList()}), so the SGA
 * manager sees a likely-significant aspect coming before the yearly evaluation, not only when they
 * happen to open the cockpit.
 *
 * Unlike the obligation reminders there is no per-item "already notified" state: the watch list is
 * computed on the fly, so this is a periodic digest (its cadence is set by how often the command is
 * scheduled). It only sends when there is at least one aspect to watch.
 */
final class AspectTrendNotifier
{
    /** Stable code of the role that manages aspect evaluations and receives these digests. */
    private const string MANAGER_ROLE = 'ems_manager';

    public function __construct(
        private readonly AspectIntensityEstimator $estimator,
        private readonly EnvironmentalAspectRepository $aspects,
        private readonly RoleRepository $roles,
        private readonly UserRepository $users,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        #[Autowire('%app.mailer_from%')]
        private readonly string $from,
    ) {
    }

    /**
     * Sends the trend digest as of the given date and returns a summary.
     *
     * @param \DateTimeImmutable $on the reference date (today)
     *
     * @return array{watched: int, emails: int} how many aspects are trending up and how many e-mails were sent
     */
    public function notify(\DateTimeImmutable $on): array
    {
        $watch = $this->estimator->watchList($this->aspects->findLinkedForIntensity(), $on);
        if ([] === $watch) {
            return ['watched' => 0, 'emails' => 0];
        }

        $role = $this->roles->findOneBy(['code' => self::MANAGER_ROLE]);
        $recipients = null !== $role ? $this->users->findActiveByRole($role) : [];

        $sent = 0;
        foreach ($recipients as $user) {
            $this->send($user, $watch, $on);
            ++$sent;
        }

        return ['watched' => \count($watch), 'emails' => $sent];
    }

    /**
     * Renders and sends one digest e-mail listing the aspects to watch.
     *
     * @param list<array{aspect: \App\Entity\EnvironmentalAspect, estimate: IntensityEstimate}> $watch the aspects trending up
     */
    private function send(User $user, array $watch, \DateTimeImmutable $on): void
    {
        $html = $this->twig->render('email/aspect_trend_alert.html.twig', [
            'user' => $user,
            'watch' => $watch,
            'today' => $on,
        ]);

        $email = (new Email())
            ->from($this->from)
            ->to($user->getEmail())
            ->subject(sprintf('Aspectos ambientales a vigilar (%d)', \count($watch)))
            ->html($html);

        $this->mailer->send($email);
    }
}
