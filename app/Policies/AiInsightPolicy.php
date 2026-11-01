<?php

namespace App\Policies;

use App\Models\AiInsight;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may read and who may (re)generate each kind of background Claude result.
 *
 * - Release risk brief: a planning surface, so every full-access role reads
 *   it; generating it (a paid call) is for those who plan releases.
 * - Standup digest: lead material about named people; org-level leads see
 *   every team, a team lead only the teams they lead.
 * - Document insights: anyone who can open the release reads them; the
 *   people who may upload documents may analyze them.
 */
class AiInsightPolicy
{
    public function view(User $user, AiInsight $insight): bool
    {
        return $insight->subject !== null && self::canView($user, $insight->kind, $insight->subject);
    }

    public static function canView(User $user, string $kind, Model $subject): bool
    {
        return match ($kind) {
            AiInsight::KIND_RELEASE_RISK => $subject instanceof Release && ! $user->hasLimitedAccess() && $user->can('view', $subject),
            AiInsight::KIND_STANDUP_DIGEST => $subject instanceof Team && $user->canAccessTeamPerformance($subject),
            AiInsight::KIND_DOCUMENT => $subject instanceof ReleaseDocument && $subject->release && $user->can('view', $subject->release),
            default => false,
        };
    }

    public static function canRequest(User $user, string $kind, Model $subject): bool
    {
        return match ($kind) {
            AiInsight::KIND_RELEASE_RISK => $subject instanceof Release && $user->canManageReleases(),
            AiInsight::KIND_STANDUP_DIGEST => $subject instanceof Team && $user->canAccessTeamPerformance($subject),
            AiInsight::KIND_DOCUMENT => self::canView($user, $kind, $subject) && ! $user->isViewer(),
            default => false,
        };
    }
}
