<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $role = $request->input('role');
        $status = $request->input('status'); // active | inactive | (all)

        $stats = $this->users->stats();

        return view('users.index', [
            'users' => $this->users->directory(compact('search', 'role', 'status'))->get(),
            'roles' => User::ROLES,
            'filters' => compact('search', 'role', 'status'),
            'stats' => $stats,
            'roleDistribution' => $stats['roleDistribution'],
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User(['role' => User::ROLE_DEVELOPER])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.index')
            ->with('success', "User “{$user->name}” created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Don't let the last active admin be demoted out of the admin role.
        if (! $this->users->canChangeRoleTo($user, $data['role'])) {
            return back()->with('error', 'You cannot change the role of the last active administrator.');
        }

        $this->users->update($user, $data);

        return redirect()->route('users.index')
            ->with('success', "User “{$user->name}” updated.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->isActive()) {
            if ($this->users->isSelf($request->user(), $user)) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if ($this->users->isLastActiveAdmin($user)) {
                return back()->with('error', 'You cannot deactivate the last active administrator.');
            }

            $this->users->deactivate($user);

            return back()->with('success', "User “{$user->name}” deactivated.");
        }

        $this->users->reactivate($user);

        return back()->with('success', "User “{$user->name}” reactivated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($this->users->isSelf($request->user(), $user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($this->users->isLastActiveAdmin($user)) {
            return back()->with('error', 'You cannot delete the last active administrator.');
        }

        $name = $user->name;
        $this->users->softDelete($user);

        return redirect()->route('users.index')
            ->with('success', "User “{$name}” deleted.");
    }
}
