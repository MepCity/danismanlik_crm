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
 * @property string|null $description
 * @property bool $is_active
 * @property-read Collection<int, ServiceWorkflowStep> $steps
 * @property-read Collection<int, ProgramVersion> $programVersions
 */
#[Fillable(['name', 'description', 'is_active'])]
final class ServiceWorkflow extends Model
{
    /** @return HasMany<ServiceWorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ServiceWorkflowStep::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProgramVersion, $this> */
    public function programVersions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
