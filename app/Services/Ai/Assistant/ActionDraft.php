<?php

namespace App\Services\Ai\Assistant;

/** A change that has passed the form rules: exactly what would run, and how to show it. */
final class ActionDraft
{
    /**
     * @param  array<string, mixed>  $input  the checked input
     * @param  array<string, mixed>  $preview  what the person sees, built from the database
     */
    public function __construct(
        public readonly array $input,
        public readonly array $preview,
    ) {}
}
