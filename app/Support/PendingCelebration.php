<?php

namespace App\Support;

/**
 * Request-scoped holder for a celebration raised while handling the request
 * (by the task observer), until ShareCelebration attaches it to the response.
 */
class PendingCelebration
{
    /** @var array{message: string, all_clear: bool}|null */
    private ?array $celebration = null;

    /** @param  array{message: string, all_clear: bool}  $celebration */
    public function set(array $celebration): void
    {
        $this->celebration = $celebration;
    }

    /** @return array{message: string, all_clear: bool}|null */
    public function pull(): ?array
    {
        return tap($this->celebration, fn () => $this->celebration = null);
    }
}
