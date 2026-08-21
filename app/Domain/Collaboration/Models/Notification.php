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
 * @property int|null $user_id
 * @property string|null $recipient_email
 * @property string|null $recipient_name
 * @property string $type
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int|null $deal_document_id
 * @property string $title
 * @property string $body
 * @property string $channel
 * @property Carbon|null $read_at
 * @property string $delivery_status
 * @property string|null $error
 */
#[Fillable([
    'user_id', 'recipient_email', 'recipient_name', 'type', 'company_id', 'program_id', 'lead_id', 'deal_id', 'deal_document_id', 'title',
    'body', 'channel', 'read_at', 'delivery_status', 'error',
])]
final class Notification extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return ['read_at' => 'datetime'];
    }
}
