<?php

namespace App\Policies;

use App\Models\TasksheetExport;
use App\Models\User;

/**
 * Background tasksheet PDFs.
 *
 * Creating one follows the report rule unchanged: leads may export any team
 * or member, a member only their own rows. Everything after that is the
 * requester's alone — leads and admins included get no view of other
 * people's exports. The file carries its requester's feedback visibility, and
 * anyone allowed the same export can generate it themselves.
 */
class TasksheetExportPolicy
{
    public function create(User $user, ?int $memberId = null): bool
    {
        return $user->isLead() || $memberId === $user->id;
    }

    public function view(User $user, TasksheetExport $export): bool
    {
        return $export->requested_by === $user->id;
    }

    /**
     * Re-checks the export rule at download time, so a demoted lead loses
     * team-wide files and any file holding lead feedback.
     */
    public function download(User $user, TasksheetExport $export): bool
    {
        return $this->view($user, $export)
            && $this->create($user, $export->member_id)
            && (! $export->include_feedback || $user->isLead());
    }

    public function retry(User $user, TasksheetExport $export): bool
    {
        return $this->view($user, $export) && $this->create($user, $export->member_id);
    }

    public function delete(User $user, TasksheetExport $export): bool
    {
        return $this->view($user, $export);
    }
}
