<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int|null $deal_document_id
 * @property int $assigned_to
 * @property int $created_by
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_at
 * @property Carbon|null $remind_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'company_id', 'lead_id', 'deal_id', 'deal_document_id', 'assigned_to', 'created_by',
    'title', 'description', 'due_at', 'remind_at', 'reminder_sent_at', 'completed_at',
])]
final class Task extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    /** @return BelongsTo<DealDocument, $this> */
    public function dealDocument(): BelongsTo
    {
        return $this->belongsTo(DealDocument::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
