<?php

declare(strict_types=1);

namespace App\Support\Outbox\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_name
 * @property array<string, mixed> $payload
 * @property int|null $actor_id
 * @property Carbon $available_at
 * @property Carbon|null $processed_at
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon $created_at
 */
#[Fillable([
    'event_name', 'payload', 'actor_id', 'available_at', 'processed_at',
    'attempts', 'last_error', 'created_at',
])]
final class OutboxMessage extends Model
{
    protected $table = 'outbox';

    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
