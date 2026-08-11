<?php

declare(strict_types=1);

namespace App\Domain\Document\Models;

use App\Support\DomainModelRegistry;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $deal_id
 * @property int|null $source_doc_template_id
 * @property int $source_program_version_id
 * @property string $name_snapshot
 * @property string|null $description_snapshot
 * @property bool $required_snapshot
 * @property array<string, mixed>|null $condition_snapshot
 * @property string $status
 * @property Carbon|null $requested_at
 * @property Carbon|null $received_at
 * @property Carbon|null $due_at
 * @property Carbon|null $validity_expires_at
 * @property string|null $notes
 * @property-read EloquentModel $deal
 * @property-read EloquentModel|null $sourceDocTemplate
 * @property-read EloquentModel $sourceProgramVersion
 * @property-read Collection<int, File> $files
 */
#[Fillable([
    'deal_id', 'source_doc_template_id', 'source_program_version_id',
    'name_snapshot', 'description_snapshot', 'required_snapshot',
    'condition_snapshot', 'status', 'requested_at', 'received_at', 'due_at',
    'validity_expires_at', 'notes',
])]
final class DealDocument extends Model
{
    /** @return BelongsTo<EloquentModel, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('deal'));
    }

    /** @return BelongsTo<EloquentModel, $this> */
    public function sourceDocTemplate(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('doc_template'));
    }

    /** @return BelongsTo<EloquentModel, $this> */
    public function sourceProgramVersion(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('program_version'));
    }

    /** @return HasMany<File, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'required_snapshot' => 'boolean',
            'condition_snapshot' => 'array',
            'requested_at' => 'datetime',
            'received_at' => 'datetime',
            'due_at' => 'datetime',
            'validity_expires_at' => 'datetime',
        ];
    }
}
