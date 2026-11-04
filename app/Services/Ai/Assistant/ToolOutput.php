<?php

namespace App\Services\Ai\Assistant;

/** What a read tool hands the model, and the references it may cite. */
final class ToolOutput
{
    /** @param  array<string, array{ref: string, kind: string, title: string, url: string}>  $refs */
    public function __construct(
        public readonly string $text,
        public readonly array $refs = [],
    ) {}
}
