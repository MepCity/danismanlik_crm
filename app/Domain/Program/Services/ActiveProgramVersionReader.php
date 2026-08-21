<?php

declare(strict_types=1);

namespace App\Domain\Program\Services;

use App\Domain\Program\DTOs\ActiveProgramVersionData;
use App\Domain\Program\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Builder;

final class ActiveProgramVersionReader
{
    public function find(int $id): ?ActiveProgramVersionData
    {
        $version = $this->query()->find($id);

        return $version === null ? null : $this->toData($version);
    }

    /** @return list<ActiveProgramVersionData> */
    public function all(): array
    {
        return $this->query()
            ->get()
            ->map(fn (ProgramVersion $version): ActiveProgramVersionData => $this->toData($version))
            ->sortBy(fn (ActiveProgramVersionData $version): string => $version->label())
            ->values()
            ->all();
    }

    /** @return Builder<ProgramVersion> */
    private function query(): Builder
    {
        return ProgramVersion::query()
            ->with('program')
            ->where('is_active', true)
            ->whereHas('program', static fn ($query) => $query->where('is_active', true));
    }

    private function toData(ProgramVersion $version): ActiveProgramVersionData
    {
        return new ActiveProgramVersionData(
            $version->id,
            $version->program->name,
            $version->call_period,
        );
    }
}
