<?php

declare(strict_types=1);

namespace App\Domain\Program\Models;

use App\Domain\Document\Models\DealDocument;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $program_version_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_required
 * @property array<string, mixed>|null $condition
 * @property list<string> $accepted_formats
 * @property int|null $validity_days
 * @property int $sort_order
 * @property bool $is_active
 * @property-read ProgramVersion $programVersion
 */
#[Fillable([
    'program_version_id', 'name', 'description', 'is_required', 'condition',
    'accepted_formats', 'validity_days', 'sort_order', 'is_active',
])]
final class DocTemplate extends Model
{
    /** @return BelongsTo<ProgramVersion, $this> */
    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    /** @return HasMany<DealDocument, $this> */
    public function dealDocuments(): HasMany
    {
        return $this->hasMany(DealDocument::class, 'source_doc_template_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'condition' => 'array',
            'accepted_formats' => 'array',
            'validity_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
