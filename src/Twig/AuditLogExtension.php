<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\UserRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Presentation helpers for the activity trail ({@see \App\Entity\AuditLog}). They turn the raw,
 * machine-oriented fields of a log entry into something a person reads: the actor's full name
 * instead of the login e-mail, and the event name (e.g. "objective.updated") into a Spanish label.
 */
class AuditLogExtension extends AbstractExtension
{
    /**
     * Login identifier (e-mail) => full name, built once per request from {@see UserRepository}.
     * Small table (one school's staff), so a single findAll is cheaper than a query per entry and
     * avoids an N+1 when a list mixes several actors.
     *
     * @var array<string, string>|null
     */
    private ?array $namesByEmail = null;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('actor_name', $this->actorName(...)),
            new TwigFilter('audit_action', $this->actionLabel(...)),
        ];
    }

    /**
     * Resolves an audit actor (a login e-mail, or null for system events) to a human name.
     * Falls back to the e-mail itself when no matching user exists (e.g. a since-deleted account),
     * so the trail never loses the "who" — it just can't prettify it.
     */
    public function actorName(?string $actor): string
    {
        if (null === $actor || '' === $actor) {
            return 'Sistema';
        }

        return $this->names()[$actor] ?? $actor;
    }

    /**
     * Turns a machine event name into a Spanish, human-readable action.
     *
     * Names follow "<subject>.<verb>" (e.g. "document.revision_approved"); only the verb carries the
     * action, since the subject is already obvious from the object's own page. Unknown verbs degrade
     * gracefully to a de-slugged, capitalised form so a newly added event is never shown as a raw
     * dotted token.
     */
    public function actionLabel(string $action): string
    {
        $verb = str_contains($action, '.') ? substr($action, strpos($action, '.') + 1) : $action;

        return self::VERB_LABELS[$verb] ?? ucfirst(str_replace('_', ' ', $verb));
    }

    /** Verb (part after the first dot of the event name) => Spanish label. */
    private const VERB_LABELS = [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'approved' => 'Aprobado',
        'cloned_from_previous' => 'Clonado del periodo anterior',
        'copied_from_previous' => 'Copiado del periodo anterior',
        'status_changed' => 'Cambio de estado',
        'cancelled' => 'Anulado',
        'archived' => 'Archivado',
        'restored' => 'Reactivado',
        'completed' => 'Marcado como hecho',
        'generated' => 'Generado',
        'revision_drafted' => 'Revisión redactada',
        'revision_edited' => 'Revisión editada',
        'revision_submitted' => 'Revisión enviada a revisar',
        'revision_reviewed' => 'Revisión revisada',
        'revision_approved' => 'Revisión aprobada',
        'revision_signed' => 'Revisión firmada',
    ];

    /**
     * @return array<string, string>
     */
    private function names(): array
    {
        if (null === $this->namesByEmail) {
            $this->namesByEmail = [];
            foreach ($this->users->findAll() as $user) {
                $this->namesByEmail[$user->getEmail()] = $user->getFullName();
            }
        }

        return $this->namesByEmail;
    }
}
