<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Document\Exceptions\DocumentFileRejected;
use Illuminate\Http\UploadedFile;
use ZipArchive;

final class DocumentFileValidator
{
    /** @var list<string> */
    private const EXTENSIONS = ['pdf', 'docx', 'xlsx', 'jpg', 'jpeg', 'png'];

    public function validate(UploadedFile $upload): string
    {
        $extension = strtolower($upload->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw DocumentFileRejected::extension();
        }

        if ($upload->getSize() === false || $upload->getSize() <= 0
            || $upload->getSize() > ((int) config('documents.max_size_kb') * 1024)) {
            throw DocumentFileRejected::tooLarge();
        }

        $path = $upload->getRealPath();

        if ($path === false) {
            throw DocumentFileRejected::mime();
        }

        $mime = match ($extension) {
            'pdf' => $this->hasPrefix($path, '%PDF-') ? 'application/pdf' : null,
            'jpg', 'jpeg' => $this->imageMime($path, 'image/jpeg'),
            'png' => $this->imageMime($path, 'image/png'),
            'docx' => $this->officeMime($path, 'word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'xlsx' => $this->officeMime($path, 'xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        };

        if ($mime === null) {
            throw DocumentFileRejected::mime();
        }

        return $mime;
    }

    private function hasPrefix(string $path, string $prefix): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, strlen($prefix));
        fclose($handle);

        return $bytes === $prefix;
    }

    private function imageMime(string $path, string $expected): ?string
    {
        $info = getimagesize($path);

        return is_array($info) && $info['mime'] === $expected ? $expected : null;
    }

    private function officeMime(string $path, string $entry, string $mime): ?string
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            return null;
        }

        $found = $archive->locateName('[Content_Types].xml') !== false
            && $archive->locateName($entry) !== false;
        $archive->close();

        return $found ? $mime : null;
    }
}
