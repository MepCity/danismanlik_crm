<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $owner_user_id
 * @property string $legal_name
 * @property string|null $tax_number
 * @property string|null $tax_office
 * @property string|null $nace_code
 * @property string $industry
 * @property string $city
 * @property string|null $district
 * @property string|null $size
 * @property int|null $employee_count
 * @property string|null $source
 * @property bool $is_active
 * @property-read User|null $owner
 * @property-read Collection<int, Contact> $contacts
 * @property-read Collection<int, Lead> $leads
 * @property-read Collection<int, Deal> $deals
 */
#[Fillable([
    'owner_user_id',
    'legal_name',
    'tax_number',
    'tax_office',
    'nace_code',
    'industry',
    'city',
    'district',
    'size',
    'employee_count',
    'source',
    'is_active',
])]
final class Company extends Model
{
    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employee_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
