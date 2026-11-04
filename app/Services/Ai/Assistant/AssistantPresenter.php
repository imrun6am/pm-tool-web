<?php

namespace App\Services\Ai\Assistant;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Services\Ai\WorkspaceAsk;
use Illuminate\Support\Collection;

/**
 * Turns the transcript into what the chat shows: the person's words, answers as
 * escaped HTML whose only links are references the server issued, and drafts.
 * Context blocks and tool results stay out of it.
 */
class AssistantPresenter
{
    /** @return list<array> */
    public static function items(AssistantConversation $conversation): array
    {
        $actions = $conversation->actions()->get()->keyBy('tool_use_id');
        $refs = collect($conversation->refs);

        $items = [];
        foreach ($conversation->messages as $index => $message) {
            if ($message['role'] === 'user') {
                $text = collect($message['content'])->where('type', 'text')->last();
                if ($text) {
                    $items[] = ['type' => 'user', 'key' => "m{$index}", 'text' => $text['text']];
                }

                continue;
            }

            $text = collect($message['content'])->where('type', 'text')->pluck('text')->implode("\n\n");
            if (trim($text) !== '') {
                $items[] = ['type' => 'assistant', 'key' => "m{$index}", 'html' => self::answer($text, $refs)];
            }

            foreach (collect($message['content'])->where('type', 'tool_use') as $use) {
                if ($action = $actions->get($use['id'])) {
                    $items[] = ['type' => 'action', 'key' => "a{$action->id}", 'action' => self::action($action)];
                }
            }
        }

        return $items;
    }

    /** Escaped by render(), so the only tags are its own, and any bold the model writes anyway. */
    private static function answer(string $text, Collection $refs): string
    {
        // render() lists a block only if every line is a bullet, so give bullets their own block.
        $text = preg_replace(['/^((?!- ).+)\n(?=- )/m', '/^(- .+)\n(?!- |\n|$)/m'], "$1\n\n", $text);

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', WorkspaceAsk::render($text, $refs));
    }

    public static function action(AssistantAction $action): array
    {
        return [
            'id' => $action->id,
            'status' => $action->status,
            'preview' => $action->preview,
            'result' => $action->result,
            'error' => $action->error,
            'confirm_url' => route('assistant.actions.confirm', $action),
            'cancel_url' => route('assistant.actions.cancel', $action),
        ];
    }
}
