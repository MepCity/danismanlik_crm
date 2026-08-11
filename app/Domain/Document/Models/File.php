<?php

declare(strict_types=1);

namespace App\Domain\Document\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deal_document_id
 * @property string $storage_key
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int $version_no
 * @property int $uploaded_by
 * @property string $scan_result
 * @property bool $is_deleted
 * @property-read DealDocument $dealDocument
 * @property-read User $uploadedBy
 */
#[Fillable([
    'deal_document_id', 'storage_key', 'original_name', 'mime_type', 'size_bytes',
    'sha256', 'version_no', 'uploaded_by', 'scan_result', 'is_deleted',
])]
final class File extends Model
{
    /** @return BelongsTo<DealDocument, $this> */
    public function dealDocument(): BelongsTo
    {
        return $this->belongsTo(DealDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version_no' => 'integer',
            'is_deleted' => 'boolean',
        ];
    }
}
