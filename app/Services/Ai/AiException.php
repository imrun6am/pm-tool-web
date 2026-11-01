<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * A Claude call that could not produce a usable answer. The message is safe to
 * show the user; `retryable` says whether trying again might help.
 */
class AiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function disabled(string $label): self
    {
        return new self("{$label} is switched off by an administrator.", false);
    }

    public static function notConfigured(): self
    {
        return new self('Claude isn’t configured (ANTHROPIC_API_KEY).', false);
    }
}
