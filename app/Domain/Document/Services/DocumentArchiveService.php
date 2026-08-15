<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Access\Services\WorkflowScopeAuthorizer;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Deal\DTO\ChecklistDeal;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Events\DocumentsArchiveDownloaded;
use App\Domain\Document\Exceptions\DocumentFileRejected;
use App\Domain\Document\Models\File;
use App\Support\Audit\ActorSource;
use App\Support\Workflow\SubjectType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

final readonly class DocumentArchiveService
{
    public function __construct(
        private ActivityRecorder $activities,
        private DocumentTransaction $transactions,
        private WorkflowScopeAuthorizer $authorization,
        private ChecklistDealGateway $deals,
    ) {}

    public function temporaryUrl(int $dealId, int $actorId): string
    {
        [$deal, $files] = $this->authorizedFiles($dealId, $actorId);

        $this->transactions->run(ActorSource::User, $actorId, function () use ($deal, $files, $actorId): void {
            $this->activities->record('deal.documents_archive_requested', [
                'document_count' => $files->count(),
            ], $actorId, dealId: $deal->id, defaultSource: 'user');
        });

        return URL::temporarySignedRoute(
            'deal-documents.archive',
            now()->addMinutes((int) config('documents.download_link_minutes')),
            ['deal' => $deal->id],
        );
    }

    public function download(int $dealId, int $actorId): StreamedResponse
    {
        [$deal, $files] = $this->authorizedFiles($dealId, $actorId);
        $downloadName = $this->downloadName($deal);

        return Response::streamDownload(function () use ($deal, $files, $actorId): void {
            $archivePath = $this->buildArchive($files);
            $stream = fopen($archivePath, 'rb');

            if ($stream === false) {
                @unlink($archivePath);
                throw DocumentFileRejected::storage();
            }

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw DocumentFileRejected::storage();
                    }
                    echo $chunk;
                }
            } finally {
                fclose($stream);
                @unlink($archivePath);
            }

            $this->transactions->run(ActorSource::User, $actorId, function () use ($deal, $files, $actorId): void {
                $fileIds = array_map(static fn (mixed $id): int => (int) $id, $files->modelKeys());
                $this->activities->record('deal.documents_archive_downloaded', [
                    'document_count' => $files->count(),
                    'file_ids' => $fileIds,
                ], $actorId, dealId: $deal->id, defaultSource: 'user');
                event(new DocumentsArchiveDownloaded($deal->id, $fileIds, $actorId));
            });
        }, $downloadName, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{ChecklistDeal, Collection<int, File>} */
    private function authorizedFiles(int $dealId, int $actorId): array
    {
        if (! $this->authorization->allows($actorId, 'document.download', SubjectType::Deal, $dealId)) {
            throw DocumentFileRejected::forbidden();
        }
        $deal = $this->deals->find($dealId);

        $files = File::query()
            ->with('dealDocument')
            ->where('is_deleted', false)
            ->where('scan_result', 'clean')
            ->whereHas('dealDocument', static fn ($query) => $query->where('deal_id', $deal->id))
            ->orderBy('deal_document_id')
            ->orderByDesc('version_no')
            ->get()
            ->unique('deal_document_id')
            ->values();

        if ($files->isEmpty()) {
            throw DocumentFileRejected::archiveEmpty();
        }

        return [$deal, $files];
    }

    /** @param Collection<int, File> $files */
    private function buildArchive(Collection $files): string
    {
        $archivePath = tempnam(sys_get_temp_dir(), 'crm-documents-');
        if ($archivePath === false) {
            throw DocumentFileRejected::archiveFailed();
        }

        $archive = new ZipArchive;
        $temporaryFiles = [];
        $archiveIsOpen = false;

        try {
            if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw DocumentFileRejected::archiveFailed();
            }
            $archiveIsOpen = true;

            foreach ($files as $index => $file) {
                $source = Storage::disk((string) config('documents.disk'))->readStream($file->storage_key);
                $temporary = tmpfile();

                if (! is_resource($source) || ! is_resource($temporary)) {
                    if (is_resource($source)) {
                        fclose($source);
                    }
                    throw DocumentFileRejected::storage();
                }

                if (stream_copy_to_stream($source, $temporary) === false) {
                    fclose($source);
                    fclose($temporary);
                    throw DocumentFileRejected::storage();
                }
                fclose($source);
                $metadata = stream_get_meta_data($temporary);
                $temporaryFiles[] = $temporary;

                if (! $archive->addFile((string) $metadata['uri'], $this->entryName($file, $index + 1))) {
                    throw DocumentFileRejected::archiveFailed();
                }
            }

            if (! $archive->close()) {
                throw DocumentFileRejected::archiveFailed();
            }
            $archiveIsOpen = false;
        } catch (Throwable $exception) {
            if ($archiveIsOpen) {
                $archive->close();
            }
            @unlink($archivePath);

            if ($exception instanceof DocumentFileRejected) {
                throw $exception;
            }

            throw DocumentFileRejected::archiveFailed();
        } finally {
            foreach ($temporaryFiles as $temporary) {
                fclose($temporary);
            }
        }

        return $archivePath;
    }

    private function entryName(File $file, int $index): string
    {
        $original = basename($file->original_name);
        $safeOriginal = preg_replace('/[^\pL\pN._ -]+/u', '-', $original) ?: 'belge';
        $document = Str::slug($file->dealDocument->name_snapshot) ?: 'belge';

        return sprintf('%02d-%s-surum-%d-%s', $index, $document, $file->version_no, $safeOriginal);
    }

    private function downloadName(ChecklistDeal $deal): string
    {
        $reference = Str::slug($deal->reference) ?: 'dosya';

        return $reference.'-guncel-belgeler.zip';
    }
}
