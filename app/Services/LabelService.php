<?php

namespace App\Services;

use App\Models\Label;
use Illuminate\Support\Collection;

/** The shared list of task labels. */
class LabelService
{
    /** @return Collection<int, Label> */
    public function all(): Collection
    {
        return Label::orderBy('name')->get();
    }

    /** The list with how many tasks use each label, for managing it. @return Collection<int, Label> */
    public function withUsage(): Collection
    {
        return Label::withCount('tasks')->orderBy('name')->get();
    }

    /** @param  array{name: string, color: string}  $data */
    public function create(array $data): Label
    {
        return Label::create($data);
    }

    /** @param  array{name: string, color: string}  $data */
    public function update(Label $label, array $data): Label
    {
        $label->update($data);

        return $label;
    }

    /** Tasks keep everything else; only the tag comes off them. */
    public function delete(Label $label): void
    {
        $label->delete();
    }
}
