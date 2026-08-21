<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $program_version_id
 * @property array<string, mixed>|null $workflow_snapshot
 * @property string $reference_no
 * @property int $status_id
 * @property Carbon $status_changed_at
 * @property int|null $pm_user_id
 * @property int $opened_by_user_id
 * @property string|null $requested_amount
 * @property string|null $application_no
 * @property Carbon|null $applied_at
 * @property Carbon|null $decided_at
 * @property string|null $result_outcome
 * @property string $priority
 * @property Carbon|null $document_requested_at
 * @property Carbon|null $first_document_received_at
 * @property Carbon|null $all_required_accepted_at
 * @property-read Company $company
 * @property-read ProgramVersion $programVersion
 * @property-read User|null $projectManager
 * @property-read User $openedBy
 * @property-read Collection<int, DealDocument> $documents
 * @property-read Collection<int, Interaction> $interactions
 * @property-read Lead|null $originatingLead
 * @property-read Status $status
 * @property-read Collection<int, StatusHistory> $statusHistory
 * @property-read Collection<int, Activity> $activities
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Notification> $notifications
 */
#[Fillable([
    'company_id', 'program_version_id', 'workflow_snapshot', 'reference_no', 'status_id', 'status_changed_at',
    'pm_user_id', 'opened_by_user_id', 'requested_amount', 'application_no',
    'applied_at', 'decided_at', 'result_outcome', 'priority', 'document_requested_at',
    'first_document_received_at', 'all_required_accepted_at',
])]
final class Deal extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<ProgramVersion, $this> */
    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
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

    /** @return HasMany<DealDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(DealDocument::class);
    }

    /** @return HasMany<Interaction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /** @return HasOne<Lead, $this> */
    public function originatingLead(): HasOne
    {
        return $this->hasOne(Lead::class, 'converted_deal_id');
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

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status_changed_at' => 'datetime',
            'workflow_snapshot' => 'array',
            'requested_amount' => 'decimal:2',
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'document_requested_at' => 'datetime',
            'first_document_received_at' => 'datetime',
            'all_required_accepted_at' => 'datetime',
        ];
    }
}
