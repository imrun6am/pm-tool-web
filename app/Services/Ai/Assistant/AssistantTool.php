<?php

namespace App\Services\Ai\Assistant;

use App\Models\User;

abstract class AssistantTool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /** JSON schema for the input. Inputs are checked by the form rules, so this only guides the model. */
    abstract public function properties(): array;

    /** @return list<string> */
    public function required(): array
    {
        return [];
    }

    /** Whether this person is offered the tool. The form rules still decide on every use. */
    public function availableTo(User $user): bool
    {
        return true;
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => [
                'type' => 'object',
                'properties' => (object) $this->properties(),
                'required' => $this->required(),
            ],
        ];
    }
}
