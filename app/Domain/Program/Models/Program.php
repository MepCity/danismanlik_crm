<?php

declare(strict_types=1);

namespace App\Domain\Program\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $institution
 * @property string $code
 * @property bool $is_active
 * @property-read Collection<int, ProgramVersion> $versions
 */
#[Fillable(['name', 'institution', 'code', 'is_active'])]
final class Program extends Model
{
    /** @return HasMany<ProgramVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
