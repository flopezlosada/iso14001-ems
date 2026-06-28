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
 *  - REVIEW: reviewing a revision before approval is done by the Responsable del Sistema (RSGMA),
 *    who reviews all document types per PC.01.0 §5.2.1.
 *  - APPROVE: approving a revision is done by the role that approves that document type (Dirección
 *    for policy/manual/procedures, RSGMA for forms) — {@see \App\Enum\DocumentType::approverRoleCode()}.
 *  - COMPLETE: closing an obligation's review cycle is done by its responsible role (same as ISSUE):
 *    the responsible is who confirms the periodic review is done for this period.
 *  - LIFECYCLE: cancelling / archiving / restoring a document is done by the RSGMA (Responsable del
 *    SGA), who owns document control (PC.01.0).
 *
 * ROLE_ADMIN bypasses all of them. Elaborator and approver are deliberately separate: e.g. the RSGMA
 * drafts a procedure but Dirección approves it.
 *
 * @extends Voter<string, Document>
 */
class DocumentVoter extends Voter
{
    public const ISSUE = 'DOCUMENT_ISSUE';
    public const REVIEW = 'DOCUMENT_REVIEW';
    public const APPROVE = 'DOCUMENT_APPROVE';
    public const COMPLETE = 'DOCUMENT_COMPLETE';
    public const LIFECYCLE = 'DOCUMENT_LIFECYCLE';

    /**
     * Code of the role that owns document control (the RSGMA). Holding it grants the lifecycle
     * actions on any document, regardless of who is its responsible/approver.
     */
    private const string DOCUMENT_CONTROL_ROLE = 'ems_manager';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::ISSUE, self::REVIEW, self::APPROVE, self::COMPLETE, self::LIFECYCLE], true) && $subject instanceof Document;
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
            self::ISSUE, self::COMPLETE => null !== $subject->getResponsibleRole() && $user->holdsRole($subject->getResponsibleRole()),
            self::REVIEW => $user->holdsRoleCode(self::DOCUMENT_CONTROL_ROLE),
            self::APPROVE => null !== ($code = $subject->getType()->approverRoleCode()) && $user->holdsRoleCode($code),
            self::LIFECYCLE => $user->holdsRoleCode(self::DOCUMENT_CONTROL_ROLE),
            default => false,
        };
    }
}
