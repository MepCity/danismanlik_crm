<?php

declare(strict_types=1);

namespace App\Domain\Program\Models;

use App\Domain\Deal\Models\Deal;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $program_id
 * @property string $call_period
 * @property Carbon|null $application_opens_at
 * @property Carbon|null $application_closes_at
 * @property string|null $description
 * @property bool $is_active
 * @property-read Program $program
 * @property-read Collection<int, DocTemplate> $docTemplates
 */
#[Fillable([
    'program_id', 'call_period', 'application_opens_at', 'application_closes_at',
    'description', 'is_active',
])]
final class ProgramVersion extends Model
{
    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<DocTemplate, $this> */
    public function docTemplates(): HasMany
    {
        return $this->hasMany(DocTemplate::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'application_opens_at' => 'datetime',
            'application_closes_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
