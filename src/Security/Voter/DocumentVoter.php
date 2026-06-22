<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorises actions on the document control workflow (PC.01.0):
 *  - ISSUE: drafting a new revision is done by the document's responsible role (the "elaborator").
 *  - APPROVE: approving a revision is done by the role that approves that document type (Dirección
 *    for policy/manual/procedures, RSGMA for forms) — {@see \App\Enum\DocumentType::approverRoleCode()}.
 *
 * ROLE_ADMIN bypasses both. Elaborator and approver are deliberately separate: e.g. the RSGMA drafts
 * a procedure but Dirección approves it.
 *
 * @extends Voter<string, Document>
 */
class DocumentVoter extends Voter
{
    public const ISSUE = 'DOCUMENT_ISSUE';
    public const APPROVE = 'DOCUMENT_APPROVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::ISSUE, self::APPROVE], true) && $subject instanceof Document;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        // getRoleNames() reads the roles from the token, honouring any role hierarchy.
        if (\in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        return match ($attribute) {
            self::ISSUE => null !== $subject->getResponsibleRole() && $user->holdsRole($subject->getResponsibleRole()),
            self::APPROVE => null !== ($code = $subject->getType()->approverRoleCode()) && $user->holdsRoleCode($code),
            default => false,
        };
    }
}
