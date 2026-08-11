<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $granted_by
 * @property string $reason
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property-read User $user
 * @property-read User $grantedBy
 */
#[Fillable(['user_id', 'granted_by', 'reason', 'expires_at', 'revoked_at'])]
final class BreakGlassGrant extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
