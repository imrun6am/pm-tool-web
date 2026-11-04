<?php

namespace App\Policies;

use App\Models\TriageImport;
use App\Models\User;

/**
 * An import is the private draft of whoever started it, closed to admins too:
 * it can hold text they chose not to send. The source of a request that was
 * sent is opened through TriageItemPolicy::viewSource instead.
 */
class TriageImportPolicy
{
    public function view(User $user, TriageImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function confirm(User $user, TriageImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function retry(User $user, TriageImport $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function delete(User $user, TriageImport $import): bool
    {
        return $import->user_id === $user->id;
    }
}
