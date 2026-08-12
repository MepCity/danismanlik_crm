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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $deal_id
 * @property int|null $deal_document_id
 * @property int $user_id
 * @property string $body
 * @property array<int, int> $mentions
 * @property string $visibility
 * @property int|null $parent_id
 * @property Carbon|null $edited_at
 */
#[Fillable([
    'company_id', 'lead_id', 'deal_id', 'deal_document_id', 'user_id', 'body', 'mentions',
    'visibility', 'parent_id', 'edited_at',
])]
final class Comment extends Model
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

    /** @return BelongsTo<Comment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Comment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'edited_at' => 'datetime',
        ];
    }
}
