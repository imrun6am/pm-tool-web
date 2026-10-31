<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * User administration, behind the manage-users middleware.
 *
 * The guard rails come from UserService, shared with the web admin screens:
 * you may not lock yourself out, and you may not remove or demote the last
 * active administrator. Deactivating or deleting also revokes the account's
 * tokens, so a desktop client loses access at once.
 */
class UserController extends ApiController
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->users->directory([
            'search' => $request->input('q'),
            'role' => $request->input('role'),
            'status' => $request->input('status'), // active | inactive | (all)
        ]);

        return $this->paginate($request, $query, UserResource::class);
    }

    /** Directory-wide totals and the role distribution, independent of filters. */
    public function stats(): JsonResponse
    {
        $stats = $this->users->stats();

        return $this->ok([
            'total' => $stats['total'],
            'active' => $stats['active'],
            'inactive' => $stats['inactive'],
            'engineers' => $stats['engineers'],
            'role_distribution' => $stats['roleDistribution'],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return $this->ok(new UserResource($user->load('teams')));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return $this->created(new UserResource($user), "User “{$user->name}” created.");
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        abort_if(
            ! $this->users->canChangeRoleTo($user, $data['role']),
            422,
            'You cannot change the role of the last active administrator.'
        );

        $this->users->update($user, $data);

        return $this->ok(new UserResource($user), "User “{$user->name}” updated.");
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->isActive()) {
            abort_if(
                $this->users->isSelf($request->user(), $user),
                422,
                'You cannot deactivate your own account.'
            );
            abort_if(
                $this->users->isLastActiveAdmin($user),
                422,
                'You cannot deactivate the last active administrator.'
            );

            $this->users->deactivate($user);

            return $this->ok(new UserResource($user), "User “{$user->name}” deactivated.");
        }

        $this->users->reactivate($user);

        return $this->ok(new UserResource($user), "User “{$user->name}” reactivated.");
    }

    /**
     * Soft delete: the account can no longer sign in and drops out of listings,
     * but everything it produced stays visible, tagged "Deleted user".
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if(
            $this->users->isSelf($request->user(), $user),
            422,
            'You cannot delete your own account.'
        );
        abort_if(
            $this->users->isLastActiveAdmin($user),
            422,
            'You cannot delete the last active administrator.'
        );

        $name = $user->name;
        $this->users->softDelete($user);

        return $this->message("User “{$name}” deleted.");
    }
}
