<?php

namespace App\Services\Ai;

/** Polishes and organizes rich-text notes before they are saved. */
interface TextEnhancer
{
    public const KINDS = ['note', 'meeting'];

    /**
     * @param  string  $kind  one of KINDS
     * @param  string  $html  the editor's current (Trix) HTML
     * @param  array{title?: ?string, type?: ?string}  $context
     * @return string sanitized, Trix-compatible HTML
     */
    public function enhance(string $kind, string $html, array $context = []): string;
}
