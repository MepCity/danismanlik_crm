<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property array<string, mixed> $snapshot
 * @property Carbon $effective_from
 * @property int $changed_by
 * @property string $reason
 * @property Carbon $created_at
 * @property-read User $changedBy
 * @property-read Collection<int, StatusHistory> $history
 */
#[Fillable(['snapshot', 'effective_from', 'changed_by', 'reason'])]
final class WorkflowRevision extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return HasMany<StatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'effective_from' => 'datetime',
        ];
    }
}
