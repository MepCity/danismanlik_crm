<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property string $channel
 * @property string $purpose
 * @property string $status
 * @property string $legal_basis
 * @property string $source
 * @property Carbon|null $disclosure_date
 * @property string|null $disclosure_method
 * @property array<string, mixed>|null $evidence
 * @property string|null $iys_reference
 * @property Carbon $effective_from
 * @property int $recorded_by
 * @property Carbon $created_at
 * @property-read Contact $contact
 * @property-read User $recordedBy
 */
#[Fillable([
    'contact_id',
    'channel',
    'purpose',
    'status',
    'legal_basis',
    'source',
    'disclosure_date',
    'disclosure_method',
    'evidence',
    'iys_reference',
    'effective_from',
    'recorded_by',
])]
final class CommunicationConsent extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'disclosure_date' => 'date',
            'evidence' => 'array',
            'effective_from' => 'datetime',
        ];
    }
}
