<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TriageImport;
use App\Services\Calendar\EventImportService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Hands back an import's original file. Images open in place; anything else downloads, so an upload can't run as a page here. */
trait ServesTriageImportFile
{
    private function importFile(TriageImport $import): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($import->file_path), 404, 'The original file is no longer available.');

        $image = in_array($import->extension, EventImportService::IMAGE_EXTENSIONS, true);

        return $disk->response(
            $import->file_path,
            $import->original_name,
            ['Content-Type' => $image ? $import->mime_type : 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
            $image ? 'inline' : 'attachment',
        );
    }
}
