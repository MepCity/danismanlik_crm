<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int|null $contact_id
 * @property int $user_id
 * @property string $type
 * @property string|null $direction
 * @property string|null $purpose
 * @property Carbon $occurred_at
 * @property string|null $outcome
 * @property string|null $note
 * @property-read Lead|null $lead
 * @property-read Deal|null $deal
 * @property-read Contact|null $contact
 * @property-read User $user
 */
#[Fillable([
    'lead_id',
    'deal_id',
    'contact_id',
    'user_id',
    'type',
    'direction',
    'purpose',
    'occurred_at',
    'outcome',
    'note',
])]
final class Interaction extends Model
{
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

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
