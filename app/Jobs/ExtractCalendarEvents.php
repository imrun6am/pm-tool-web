<?php

namespace App\Jobs;

use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use App\Models\EventImport;
use App\Services\Calendar\EventExtractor;
use App\Services\Calendar\EventImportService;
use App\Services\Calendar\UnreadableDocumentException;
use App\Services\Kpi\MissingApiKeyException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads an uploaded file and proposes calendar events for review. Progress is
 * saved per chunk, so a retry after a timeout or overload resumes rather than
 * paying for the finished chunks again.
 */
class ExtractCalendarEvents implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds; a long PDF at medium effort can take several minutes. Keep DB_QUEUE_RETRY_AFTER above it. */
    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $importId) {}

    public function handle(EventImportService $imports, EventExtractor $extractor): void
    {
        $import = EventImport::find($this->importId);
        if (! $import || ! $import->isRunning()) {
            return;
        }

        try {
            $imports->process($import, $extractor);
        } catch (MissingApiKeyException|UnreadableDocumentException|AuthenticationException|PermissionDeniedException|BadRequestException $e) {
            $this->fail($e); // a configuration, file or request error: retrying cannot succeed
        }
    }

    /** Final failure (after retries, or at once for the errors above): shown on the import. */
    public function failed(?Throwable $e): void
    {
        $message = match (true) {
            $e instanceof MissingApiKeyException => 'Claude is not configured: set ANTHROPIC_API_KEY in .env. (.ics files import without it.)',
            $e instanceof BadRequestException => 'Claude could not read this file: '.$e->getMessage(),
            default => $e?->getMessage() ?? 'Unknown error',
        };

        EventImport::whereKey($this->importId)
            ->whereIn('status', [EventImport::STATUS_PENDING, EventImport::STATUS_PROCESSING])
            ->update(['status' => EventImport::STATUS_FAILED, 'error' => mb_strimwidth($message, 0, 500, '…')]);
    }
}
