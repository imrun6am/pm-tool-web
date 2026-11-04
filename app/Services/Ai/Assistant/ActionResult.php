<?php

namespace App\Services\Ai\Assistant;

/** A change that ran. */
final class ActionResult
{
    /** @param  array{ref: string, kind: string, title: string, url: string}|null  $ref */
    public function __construct(
        public readonly string $label,
        public readonly ?string $url,
        public readonly string $forModel,
        public readonly ?array $ref = null,
    ) {}
}
