<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Models\User;
use App\Support\DomainModelRegistry;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $owner_user_id
 * @property string|null $source
 * @property int|null $interested_program_version_id
 * @property string $status
 * @property Carbon|null $next_call_at
 * @property string|null $lost_reason
 * @property-read Company $company
 * @property-read User $owner
 * @property-read EloquentModel|null $interestedProgramVersion
 * @property-read Collection<int, Interaction> $interactions
 */
#[Fillable([
    'company_id',
    'owner_user_id',
    'source',
    'interested_program_version_id',
    'status',
    'next_call_at',
    'lost_reason',
])]
final class Lead extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<EloquentModel, $this> */
    public function interestedProgramVersion(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('program_version'));
    }

    /** @return HasMany<Interaction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_call_at' => 'datetime',
        ];
    }
}
