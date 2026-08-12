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
 * @property int|null $actor_id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int|null $deal_document_id
 * @property string $action
 * @property array<string, mixed> $payload
 * @property string $source
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable([
    'actor_id', 'company_id', 'lead_id', 'deal_id', 'deal_document_id', 'action', 'payload',
    'source', 'ip_address', 'user_agent',
])]
final class Activity extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
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
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
