<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Models\User;
use App\Support\DomainModelRegistry;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int $user_id
 * @property string $type
 * @property Carbon $occurred_at
 * @property int|null $duration_minutes
 * @property string|null $outcome
 * @property string|null $note
 * @property-read Lead|null $lead
 * @property-read EloquentModel|null $deal
 * @property-read User $user
 */
#[Fillable([
    'lead_id',
    'deal_id',
    'user_id',
    'type',
    'occurred_at',
    'duration_minutes',
    'outcome',
    'note',
])]
final class Interaction extends Model
{
    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<EloquentModel, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('deal'));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }
}
