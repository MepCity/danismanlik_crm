<?php

declare(strict_types=1);

namespace App\Domain\Program\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $name
 * @property string $institution
 * @property string $code
 * @property bool $is_active
 * @property-read Collection<int, ProgramVersion> $versions
 * @property-read ProgramVersion|null $latestVersion
 */
#[Fillable(['name', 'institution', 'code', 'is_active'])]
final class Program extends Model
{
    /** @return HasMany<ProgramVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }

    /** @return HasOne<ProgramVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(ProgramVersion::class)->latestOfMany();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
