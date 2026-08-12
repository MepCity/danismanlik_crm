<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $full_name
 * @property string|null $title
 * @property string|null $phone
 * @property string|null $email
 * @property string $data_source
 * @property bool $is_primary
 * @property bool $is_active
 * @property bool|null $consent_call
 * @property bool|null $consent_sms
 * @property bool|null $consent_email
 * @property bool $do_not_call
 * @property-read Company $company
 * @property-read Collection<int, CommunicationConsent> $communicationConsents
 */
#[Fillable([
    'company_id',
    'full_name',
    'title',
    'phone',
    'email',
    'data_source',
    'is_primary',
    'is_active',
    'consent_call',
    'consent_sms',
    'consent_email',
    'do_not_call',
])]
final class Contact extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<CommunicationConsent, $this> */
    public function communicationConsents(): HasMany
    {
        return $this->hasMany(CommunicationConsent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'consent_call' => 'boolean',
            'consent_sms' => 'boolean',
            'consent_email' => 'boolean',
            'do_not_call' => 'boolean',
        ];
    }
}
