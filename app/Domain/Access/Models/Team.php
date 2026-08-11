<?php

declare(strict_types=1);

namespace App\Domain\Access\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $manager_id
 * @property bool $is_active
 * @property-read User $manager
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, TeamMember> $memberships
 */
#[Fillable(['name', 'manager_id', 'is_active'])]
final class Team extends Model
{
    /** @return BelongsTo<User, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<TeamMember, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
