<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\RiskAssessment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorises approving a {@see RiskAssessment} revision (PC.03.0 §5.2): recording who signed it off
 * and when. Approval is Dirección's competence over the F.08.0 register, extended to the RSGMA
 * (Responsable del SGA) who runs the system day to day.
 *
 * ROLE_ADMIN bypasses the check. Editing a valuation is gated separately by area write permission
 * ({@see AreaVoter}); approving is the heavier, role-bound step.
 *
 * @extends Voter<string, RiskAssessment>
 */
class RiskAssessmentVoter extends Voter
{
    public const APPROVE = 'RISK_ASSESSMENT_APPROVE';

    /**
     * Codes of the roles allowed to approve a valuation: Dirección (owner of the F.08.0) and the
     * RSGMA.
     *
     * @var list<string>
     */
    private const array APPROVER_ROLES = ['direction', 'ems_manager'];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::APPROVE === $attribute && $subject instanceof RiskAssessment;
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

        foreach (self::APPROVER_ROLES as $code) {
            if ($user->holdsRoleCode($code)) {
                return true;
            }
        }

        return false;
    }
}
