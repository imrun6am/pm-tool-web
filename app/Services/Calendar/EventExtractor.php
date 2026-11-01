<?php

namespace App\Services\Calendar;

/** Finds calendar events in one document, or one chunk of a longer one. */
interface EventExtractor
{
    /** Media types sent to the model as-is rather than converted to text. */
    public const NATIVE_MIME_TYPES = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp',
    ];

    /**
     * @param  array{kind: 'file', path: string, mime: string, name: string}|array{kind: 'text', text: string, part: int, parts: int, name: string}  $source
     *                                                                                                                                                        a PDF/image on disk, or a text chunk
     * @param  array{today: string, timezone: string, releases: list<string>}  $context
     * @return array{events: list<array<string, mixed>>, usage: array{input_tokens: int, output_tokens: int}}
     *                                                                                                        raw events in the schema's shape; the caller normalizes them
     */
    public function extract(array $source, array $context): array;
}
