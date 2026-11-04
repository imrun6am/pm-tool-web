<?php

namespace App\Services;

use App\Models\SavedView;
use App\Models\User;
use Illuminate\Support\Collection;

class SavedViewService
{
    /**
     * The views `$user` can open on a page: their own first, then shared ones.
     *
     * @return Collection<int, SavedView>
     */
    public function forPage(User $user, string $page): Collection
    {
        return SavedView::availableTo($user)
            ->where('page', $page)
            ->with('user')
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->get();
    }

    /** @param  array<string, mixed>  $filters  anything the page doesn't accept is dropped */
    public function save(User $user, string $page, string $name, array $filters, bool $shared): SavedView
    {
        return SavedView::updateOrCreate(
            ['user_id' => $user->id, 'page' => $page, 'name' => $name],
            ['filters' => $this->clean($page, $filters), 'is_shared' => $shared],
        );
    }

    public function delete(SavedView $view): void
    {
        $view->delete();
    }

    /** @param  array<string, mixed>  $filters @return array<string, scalar> */
    private function clean(string $page, array $filters): array
    {
        return collect($filters)
            ->only(SavedView::PAGES[$page])
            ->filter(fn ($value) => is_scalar($value) && $value !== '')
            ->all();
    }
}
