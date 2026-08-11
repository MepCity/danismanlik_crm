<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $change_type
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 * @property int $changed_by
 * @property string $reason
 * @property-read User $changedBy
 */
#[Fillable([
    'subject_type',
    'subject_id',
    'change_type',
    'old_value',
    'new_value',
    'changed_by',
    'reason',
])]
final class RolePermissionHistory extends Model
{
    protected $table = 'role_permission_history';

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }
}
