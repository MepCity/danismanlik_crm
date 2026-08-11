<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $owner_user_id
 * @property string|null $source
 * @property int|null $interested_program_version_id
 * @property int $status_id
 * @property Carbon|null $next_call_at
 * @property string|null $lost_reason
 * @property-read Company $company
 * @property-read User $owner
 * @property-read ProgramVersion|null $interestedProgramVersion
 * @property-read Collection<int, Interaction> $interactions
 * @property-read Status $status
 * @property-read Collection<int, StatusHistory> $statusHistory
 */
#[Fillable([
    'company_id',
    'owner_user_id',
    'source',
    'interested_program_version_id',
    'status_id',
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

    /** @return BelongsTo<ProgramVersion, $this> */
    public function interestedProgramVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    /** @return HasMany<Interaction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /** @return BelongsTo<Status, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** @return HasMany<StatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_call_at' => 'datetime',
        ];
    }
}
