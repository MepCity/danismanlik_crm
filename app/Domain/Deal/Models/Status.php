<?php

declare(strict_types=1);

namespace App\Domain\Deal\Models;

use App\Domain\Crm\Models\Lead;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string $type
 * @property string $color
 * @property int $sort_order
 * @property bool $is_terminal
 * @property bool $is_active
 * @property-read Collection<int, Lead> $leads
 * @property-read Collection<int, Deal> $deals
 * @property-read Collection<int, Transition> $outgoingTransitions
 * @property-read Collection<int, Transition> $incomingTransitions
 * @property-read Collection<int, StatusHistory> $history
 */
#[Fillable(['code', 'label', 'type', 'color', 'sort_order', 'is_terminal', 'is_active'])]
final class Status extends Model
{
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

    /** @return HasMany<Transition, $this> */
    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'from_status_id');
    }

    /** @return HasMany<Transition, $this> */
    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(Transition::class, 'to_status_id');
    }

    /** @return HasMany<StatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_terminal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
