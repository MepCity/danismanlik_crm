<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

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
 * @property int $program_version_id
 * @property string $reference_no
 * @property string $status
 * @property Carbon $status_changed_at
 * @property int|null $pm_user_id
 * @property int $opened_by_user_id
 * @property string|null $requested_amount
 * @property string|null $application_no
 * @property Carbon|null $applied_at
 * @property Carbon|null $decided_at
 * @property string $priority
 * @property Carbon|null $document_requested_at
 * @property Carbon|null $first_document_received_at
 * @property Carbon|null $all_required_accepted_at
 * @property-read EloquentModel $company
 * @property-read EloquentModel $programVersion
 * @property-read User|null $projectManager
 * @property-read User $openedBy
 * @property-read Collection<int, EloquentModel> $documents
 * @property-read Collection<int, EloquentModel> $interactions
 */
#[Fillable([
    'company_id', 'program_version_id', 'reference_no', 'status', 'status_changed_at',
    'pm_user_id', 'opened_by_user_id', 'requested_amount', 'application_no',
    'applied_at', 'decided_at', 'priority', 'document_requested_at',
    'first_document_received_at', 'all_required_accepted_at',
])]
final class Deal extends Model
{
    /** @return BelongsTo<EloquentModel, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('company'));
    }

    /** @return BelongsTo<EloquentModel, $this> */
    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(DomainModelRegistry::resolve('program_version'));
    }

    /** @return BelongsTo<User, $this> */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /** @return HasMany<EloquentModel, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DomainModelRegistry::resolve('deal_document'));
    }

    /** @return HasMany<EloquentModel, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(DomainModelRegistry::resolve('interaction'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
            'requested_amount' => 'decimal:2',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'document_requested_at' => 'datetime',
            'first_document_received_at' => 'datetime',
            'all_required_accepted_at' => 'datetime',
        ];
    }
}
