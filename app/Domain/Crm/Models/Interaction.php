<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int $user_id
 * @property string $type
 * @property Carbon $occurred_at
 * @property int|null $duration_minutes
 * @property string|null $outcome
 * @property string|null $note
 * @property-read EloquentModel $subject
 * @property-read User $user
 */
#[Fillable([
    'subject_type',
    'subject_id',
    'user_id',
    'type',
    'occurred_at',
    'duration_minutes',
    'outcome',
    'note',
])]
final class Interaction extends Model
{
    /** @return MorphTo<EloquentModel, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
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
