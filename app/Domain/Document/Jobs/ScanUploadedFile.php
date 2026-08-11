<?php

declare(strict_types=1);

namespace App\Domain\Document\Jobs;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Document\Events\DocumentStatusChanged;
use App\Domain\Document\Models\File;
use App\Domain\Document\Scanning\ScanResult;
use App\Domain\Document\Scanning\VirusScanner;
use App\Domain\Document\Services\DealDocumentCompletion;
use App\Domain\Document\Services\DocumentTransaction;
use App\Support\Audit\ActorSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ScanUploadedFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $fileId) {}

    public function handle(
        VirusScanner $scanner,
        DocumentTransaction $transactions,
        ActivityRecorder $activities,
        DealDocumentCompletion $completion,
    ): void {
        $file = File::query()->findOrFail($this->fileId);
        $stream = Storage::disk((string) config('documents.disk'))->readStream($file->storage_key);

        if (! is_resource($stream)) {
            throw new RuntimeException(trans('documents.errors.storage'));
        }

        $temporary = tempnam(sys_get_temp_dir(), 'document-scan-');

        if ($temporary === false) {
            fclose($stream);
            throw new RuntimeException(trans('documents.errors.scan_failed'));
        }

        $target = fopen($temporary, 'wb');

        if ($target === false) {
            fclose($stream);
            throw new RuntimeException(trans('documents.errors.scan_failed'));
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        try {
            $result = $scanner->scan($temporary);
        } finally {
            @unlink($temporary);
        }

        $transactions->run(ActorSource::Automation, null, function () use ($result, $activities, $completion): void {
            $file = File::query()->with('dealDocument.deal')->lockForUpdate()->findOrFail($this->fileId);
            $file->update(['scan_result' => $result->value]);
            $activities->record('document.scan_completed', [
                'file_id' => $file->id, 'scan_result' => $result->value,
            ], dealDocumentId: $file->deal_document_id, defaultSource: 'automation');

            if ($result === ScanResult::Infected) {
                $document = $file->dealDocument;
                $from = $document->status;
                $document->update([
                    'status' => 'rejected',
                    'notes' => trans('documents.scan.infected_reason'),
                ]);
                $completion->refresh($document->deal_id);
                $activities->record('document.status_changed', [
                    'from_status' => $from,
                    'to_status' => 'rejected',
                    'reason' => trans('documents.scan.infected_reason'),
                ], dealDocumentId: $document->id, defaultSource: 'automation');
                event(new DocumentStatusChanged($document->id, $from, 'rejected'));
            }
        });
    }
}
