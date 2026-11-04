<?php

namespace App\Jobs;

use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use App\Models\TriageImport;
use App\Services\Ai\AiException;
use App\Services\Calendar\UnreadableDocumentException;
use App\Services\TriageImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads a file, screenshot or pasted text and proposes requests for review.
 * Progress is saved per part, so a retry after a timeout or overload resumes
 * rather than paying for the finished parts again.
 */
class ExtractTriageRequests implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; a long document can take several minutes. Keep DB_QUEUE_RETRY_AFTER above it. */
    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $importId) {}

    public function handle(TriageImportService $imports): void
    {
        $import = TriageImport::find($this->importId);
        if (! $import || ! $import->isRunning()) {
            return;
        }

        try {
            $imports->process($import);
        } catch (UnreadableDocumentException $e) {
            $this->fail($e);
        } catch (AiException $e) {
            // Waiting won't fix a switched-off feature or a file the API rejects, so don't retry those.
            if (! $e->retryable || self::isPermanent($e->getPrevious())) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /** Final failure (after retries, or at once for the errors above): shown on the import. */
    public function failed(?Throwable $e): void
    {
        TriageImport::whereKey($this->importId)
            ->whereIn('status', [TriageImport::STATUS_PENDING, TriageImport::STATUS_PROCESSING])
            ->update(['status' => TriageImport::STATUS_FAILED, 'error' => mb_strimwidth(self::message($e), 0, 500, '…')]);
    }

    private static function isPermanent(?Throwable $cause): bool
    {
        return $cause instanceof BadRequestException
            || $cause instanceof AuthenticationException
            || $cause instanceof PermissionDeniedException;
    }

    /** Our own exceptions carry words meant for people; anything else stays out of the page. */
    private static function message(?Throwable $e): string
    {
        return match (true) {
            $e instanceof AiException && $e->getPrevious() instanceof BadRequestException => 'The AI couldn’t read this. A file may be damaged, password-protected or too long. Try a smaller or clearer copy.',
            $e instanceof AiException && self::isPermanent($e->getPrevious()) => 'AI isn’t set up correctly, so an admin needs to check the API key.',
            $e instanceof AiException, $e instanceof UnreadableDocumentException => $e->getMessage(),
            default => 'Reading this took too long or something went wrong. Please try again.',
        };
    }
}
