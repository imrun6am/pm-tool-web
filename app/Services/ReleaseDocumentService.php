<?php

namespace App\Services;

use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Release attachments and the disk beneath them.
 *
 * Files live on the private `local` disk and are only ever served through
 * `download()`, which runs behind the caller's authorization. Centralising the
 * disk writes here is what keeps the stored path and the database row from
 * drifting apart — a delete that removed the row but not the file, or the
 * reverse, would leave the two out of step.
 */
class ReleaseDocumentService
{
    private const DISK = 'local';

    /** @return Collection<int, ReleaseDocument> */
    public function forRelease(Release $release): Collection
    {
        return $release->documents()->with('uploader')->get();
    }

    /**
     * The private disk, typed as the concrete adapter.
     *
     * `Storage::disk()` is declared as returning the base Filesystem contract,
     * which does not carry `download()` — so static analysis cannot see the
     * method without this narrowing.
     */
    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);

        return $disk;
    }

    public function store(Release $release, UploadedFile $file, User $uploader): ReleaseDocument
    {
        $path = $file->store("releases/{$release->id}", self::DISK);

        return $release->documents()->create([
            'uploaded_by' => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /** Stream the file under its original name. */
    public function download(Release $release, ReleaseDocument $document): StreamedResponse
    {
        $this->assertBelongsTo($release, $document);
        abort_unless($this->disk()->exists($document->path), 404);

        return $this->disk()->download($document->path, $document->original_name);
    }

    /** Remove the stored file and its row together. */
    public function delete(Release $release, ReleaseDocument $document): void
    {
        $this->assertBelongsTo($release, $document);

        $this->disk()->delete($document->path);
        $document->delete();
    }

    /**
     * A document reached through the wrong release is a 404, not somebody
     * else's file.
     */
    private function assertBelongsTo(Release $release, ReleaseDocument $document): void
    {
        abort_unless($document->release_id === $release->id, 404);
    }
}
