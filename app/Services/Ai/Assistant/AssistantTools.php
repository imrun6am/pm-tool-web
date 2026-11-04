<?php

namespace App\Services\Ai\Assistant;

use App\Models\User;

/** Every tool the assistant has, and which of them a person is offered. */
class AssistantTools
{
    private const ALL = [
        Tools\SearchWorkspace::class,
        Tools\MyWork::class,
        Tools\ListTasks::class,
        Tools\ListEvents::class,
        Tools\FindPeople::class,
        Tools\ListTeamsAndReleases::class,
        Tools\GetTasksheet::class,
        Tools\TeamOverview::class,
        Tools\ReleaseFacts::class,
        Tools\CreateEvent::class,
        Tools\CreateTask::class,
        Tools\AddToTasksheet::class,
        Tools\UpdateTask::class,
        Tools\AssignTask::class,
        Tools\AddComment::class,
        Tools\LogTime::class,
        Tools\AddNote::class,
        Tools\PostReleaseStatus::class,
    ];

    /** @return array<string, AssistantTool> by name, in a fixed order so the prompt cache holds */
    public function for(User $user): array
    {
        $tools = [];
        foreach (self::ALL as $class) {
            $tool = app($class);
            if ($tool->availableTo($user)) {
                $tools[$tool->name()] = $tool;
            }
        }

        return $tools;
    }

    /** An action tool by name, whoever is asking: the form rules decide when it runs. */
    public function action(string $name): ?ActionTool
    {
        foreach (self::ALL as $class) {
            if (is_subclass_of($class, ActionTool::class) && ($tool = app($class))->name() === $name) {
                return $tool;
            }
        }

        return null;
    }
}
