<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

use App\Domain\Crm\Models\Lead;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int $status_id
 * @property string $status_label_snapshot
 * @property int|null $workflow_revision_id
 * @property int|null $transition_id
 * @property Carbon $entered_at
 * @property Carbon|null $exited_at
 * @property int $changed_by
 * @property string|null $reason
 * @property-read Lead|null $lead
 * @property-read Deal|null $deal
 * @property-read Status $status
 * @property-read WorkflowRevision|null $workflowRevision
 * @property-read Transition|null $transition
 * @property-read User $changedBy
 */
#[Fillable([
    'lead_id', 'deal_id', 'status_id', 'status_label_snapshot',
    'workflow_revision_id', 'transition_id', 'entered_at', 'exited_at',
    'changed_by', 'reason',
])]
final class StatusHistory extends Model
{
    protected $table = 'status_history';

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Deal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** @return BelongsTo<Status, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /** @return BelongsTo<WorkflowRevision, $this> */
    public function workflowRevision(): BelongsTo
    {
        return $this->belongsTo(WorkflowRevision::class);
    }

    /** @return BelongsTo<Transition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(Transition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }
}
