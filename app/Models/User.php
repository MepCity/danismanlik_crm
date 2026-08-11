<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property string|null $data_scope
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Team> $managedTeams
 * @property-read Collection<int, TeamMember> $teamMemberships
 * @property-read Collection<int, BreakGlassGrant> $breakGlassGrants
 * @property-read Collection<int, BreakGlassGrant> $grantedBreakGlassGrants
 * @property-read Collection<int, RolePermissionHistory> $permissionChanges
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'deactivated_at', 'last_login_at', 'data_scope'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Team, $this> */
    public function managedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    /** @return HasMany<TeamMember, $this> */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /** @return HasMany<BreakGlassGrant, $this> */
    public function breakGlassGrants(): HasMany
    {
        return $this->hasMany(BreakGlassGrant::class);
    }

    /** @return HasMany<BreakGlassGrant, $this> */
    public function grantedBreakGlassGrants(): HasMany
    {
        return $this->hasMany(BreakGlassGrant::class, 'granted_by');
    }

    /** @return HasMany<RolePermissionHistory, $this> */
    public function permissionChanges(): HasMany
    {
        return $this->hasMany(RolePermissionHistory::class, 'changed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
