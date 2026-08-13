<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
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
 * @property Carbon|null $app_authentication_enabled_at
 * @property string|null $data_scope
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Team> $managedTeams
 * @property-read Collection<int, TeamMember> $teamMemberships
 * @property-read Collection<int, BreakGlassGrant> $breakGlassGrants
 * @property-read Collection<int, BreakGlassGrant> $grantedBreakGlassGrants
 * @property-read Collection<int, RolePermissionHistory> $permissionChanges
 * @property-read Collection<int, Lead> $ownedLeads
 * @property-read Collection<int, Interaction> $interactions
 * @property-read Collection<int, CommunicationConsent> $recordedCommunicationConsents
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'deactivated_at', 'last_login_at', 'data_scope'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'operations' && $this->is_active;
    }

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

    /** @return HasMany<Lead, $this> */
    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_user_id');
    }

    /** @return HasMany<Deal, $this> */
    public function managedDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pm_user_id');
    }

    /** @return HasMany<Interaction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /** @return HasMany<CommunicationConsent, $this> */
    public function recordedCommunicationConsents(): HasMany
    {
        return $this->hasMany(CommunicationConsent::class, 'recorded_by');
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
            'app_authentication_enabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->isDirty('app_authentication_secret')) {
                return;
            }

            $user->app_authentication_enabled_at = filled($user->app_authentication_secret) ? now() : null;
        });
    }
}
