<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $from_status_id
 * @property int $to_status_id
 * @property string|null $required_permission
 * @property array<string, mixed>|null $condition
 * @property bool $is_active
 * @property-read Status $fromStatus
 * @property-read Status $toStatus
 * @property-read Collection<int, StatusHistory> $history
 */
#[Fillable(['from_status_id', 'to_status_id', 'required_permission', 'condition', 'is_active'])]
final class Transition extends Model
{
    /** @return BelongsTo<Status, $this> */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id');
    }

    /** @return BelongsTo<Status, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id');
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
            'condition' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
