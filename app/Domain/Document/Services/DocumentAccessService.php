<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Document\Events\DocumentAccessRequested;
use App\Domain\Document\Events\DocumentDownloaded;
use App\Domain\Document\Exceptions\DocumentFileRejected;
use App\Domain\Document\Models\File;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DocumentAccessService
{
    public function __construct(
        private OperationPermissionChecker $permissions,
        private ActivityRecorder $activities,
        private DocumentTransaction $transactions,
    ) {}

    public function temporaryUrl(int $fileId, int $actorId): string
    {
        $file = $this->authorizedFile($fileId, $actorId);

        $this->transactions->run(ActorSource::User, $actorId, function () use ($file, $actorId): void {
            $this->activities->record('document.access_requested', [
                'file_id' => $file->id, 'version_no' => $file->version_no,
            ], $actorId, dealDocumentId: $file->deal_document_id, defaultSource: 'user');
            event(new DocumentAccessRequested($file->id, $actorId));
        });

        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes((int) config('documents.download_link_minutes')),
            ['file' => $file->id],
        );
    }

    public function download(int $fileId, int $actorId): StreamedResponse
    {
        $file = $this->authorizedFile($fileId, $actorId);
        $stream = Storage::disk((string) config('documents.disk'))->readStream($file->storage_key);

        if (! is_resource($stream)) {
            throw DocumentFileRejected::storage();
        }

        return Response::streamDownload(function () use ($stream, $file, $actorId): void {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    fclose($stream);

                    return;
                }

                echo $chunk;
            }
            fclose($stream);

            $this->transactions->run(ActorSource::User, $actorId, function () use ($file, $actorId): void {
                $this->activities->record('document.downloaded', [
                    'file_id' => $file->id, 'version_no' => $file->version_no,
                ], $actorId, dealDocumentId: $file->deal_document_id, defaultSource: 'user');
                event(new DocumentDownloaded($file->id, $actorId));
            });
        }, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizedFile(int $fileId, int $actorId): File
    {
        if (! $this->permissions->allows($actorId, 'document.download')) {
            throw DocumentFileRejected::forbidden();
        }

        $file = File::query()->where('is_deleted', false)->findOrFail($fileId);

        if ($file->scan_result !== 'clean') {
            throw DocumentFileRejected::unavailable();
        }

        return $file;
    }
}
