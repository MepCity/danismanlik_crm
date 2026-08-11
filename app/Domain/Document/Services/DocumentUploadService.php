<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Access\Services\OperationPermissionChecker;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Document\DTOs\DocumentUploadResult;
use App\Domain\Document\Events\DocumentStatusChanged;
use App\Domain\Document\Events\DocumentUploaded;
use App\Domain\Document\Exceptions\DocumentFileRejected;
use App\Domain\Document\Exceptions\DocumentStatusRejected;
use App\Domain\Document\Jobs\ScanUploadedFile;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Support\Audit\ActorSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class DocumentUploadService
{
    public function __construct(
        private DocumentFileValidator $validator,
        private DocumentTransaction $transactions,
        private OperationPermissionChecker $permissions,
        private ActivityRecorder $activities,
        private DealDocumentCompletion $completion,
    ) {}

    public function upload(int $documentId, UploadedFile $upload, int $actorId): DocumentUploadResult
    {
        if (! $this->permissions->allows($actorId, 'document.upload')) {
            throw DocumentFileRejected::forbidden();
        }

        $mime = $this->validator->validate($upload);
        $path = $upload->getRealPath();
        $hash = $path === false ? false : hash_file('sha256', $path);

        if ($hash === false) {
            throw DocumentFileRejected::mime();
        }

        if (File::query()->where('deal_document_id', $documentId)->where('sha256', $hash)->exists()) {
            throw DocumentFileRejected::duplicate();
        }

        $storageKey = (string) Str::uuid();
        $disk = Storage::disk((string) config('documents.disk'));
        $stream = fopen($upload->getRealPath(), 'rb');

        if ($stream === false || ! $disk->writeStream($storageKey, $stream, ['visibility' => 'private'])) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw DocumentFileRejected::storage();
        }
        fclose($stream);

        try {
            $result = $this->transactions->run(ActorSource::User, $actorId, function () use ($documentId, $upload, $actorId, $mime, $hash, $storageKey): DocumentUploadResult {
                $document = DealDocument::query()->with(['deal', 'sourceDocTemplate'])->lockForUpdate()->findOrFail($documentId);

                if ($document->status === 'not_required') {
                    throw DocumentStatusRejected::transition();
                }

                if (File::query()->where('deal_document_id', $documentId)->where('sha256', $hash)->exists()) {
                    throw DocumentFileRejected::duplicate();
                }

                $version = (int) File::query()->where('deal_document_id', $documentId)->max('version_no') + 1;
                $file = File::query()->create([
                    'deal_document_id' => $documentId,
                    'storage_key' => $storageKey,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size_bytes' => (int) $upload->getSize(),
                    'sha256' => $hash,
                    'version_no' => $version,
                    'uploaded_by' => $actorId,
                    'scan_result' => 'pending',
                ]);
                $now = Carbon::now();
                $from = $document->status;
                $validityDays = $document->sourceDocTemplate?->validity_days;
                $document->update([
                    'status' => 'uploaded',
                    'received_at' => $now,
                    'validity_expires_at' => $validityDays === null ? null : $now->copy()->addDays($validityDays),
                ]);
                $first = $this->completion->documentReceived($document->deal_id, $now);
                $this->activities->record('document.uploaded', [
                    'file_id' => $file->id,
                    'version_no' => $version,
                    'original_name' => $file->original_name,
                    'document_name' => $document->name_snapshot,
                ], $actorId, dealDocumentId: $document->id, defaultSource: 'user');
                $this->activities->record('document.status_changed', [
                    'from_status' => $from, 'to_status' => 'uploaded',
                ], $actorId, dealDocumentId: $document->id, defaultSource: 'user');
                event(new DocumentUploaded((string) $document->id, (string) $file->id, (string) $actorId));
                event(new DocumentStatusChanged($document->id, $from, 'uploaded', $actorId));
                ScanUploadedFile::dispatch($file->id)->afterCommit();

                return new DocumentUploadResult($file, $first);
            });
        } catch (Throwable $exception) {
            if (! File::query()->where('storage_key', $storageKey)->exists()) {
                $disk->delete($storageKey);
            }
            throw $exception;
        }

        return $result;
    }
}
