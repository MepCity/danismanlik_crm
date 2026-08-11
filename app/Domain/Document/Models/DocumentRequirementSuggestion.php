<?php

declare(strict_types=1);

namespace App\Domain\Document\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $deal_document_id
 * @property string $reason
 * @property array<string, mixed> $reason_parameters
 * @property string $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property-read DealDocument $dealDocument
 * @property-read User|null $decidedBy
 */
#[Fillable([
    'deal_document_id', 'reason', 'reason_parameters', 'status', 'decided_by', 'decided_at',
])]
final class DocumentRequirementSuggestion extends Model
{
    /** @return BelongsTo<DealDocument, $this> */
    public function dealDocument(): BelongsTo
    {
        return $this->belongsTo(DealDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reason_parameters' => 'array',
            'decided_at' => 'datetime',
        ];
    }
}
