<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SaveTeam
{
    /** @param array<string, mixed> $data */
    public function execute(?Team $team, array $data, User $actor): Team
    {
        $reason = trim((string) Arr::pull($data, 'change_reason'));
        $memberIds = array_map('intval', (array) Arr::pull($data, 'member_ids', []));

        if ($reason === '') {
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

        $managerId = (int) ($data['manager_id'] ?? 0);
        $manager = User::query()->where('is_active', true)->find($managerId);
        if ($manager === null) {
            throw new InvalidArgumentException(__('management.validation.team_manager_required'));
        }

        return DB::transaction(function () use ($team, $data, $actor, $reason, $memberIds): Team {
            $old = $team === null ? null : [
                'name' => $team->name,
                'manager_id' => $team->manager_id,
                'member_ids' => $team->members()->pluck('users.id')->sort()->values()->all(),
                'is_active' => $team->is_active,
            ];

            $team ??= new Team;
            $team->fill($data);
            $team->save();

            $team->members()->sync(
                collect($memberIds)->mapWithKeys(
                    static fn (int $userId): array => [$userId => ['role' => 'member']],
                )->all(),
            );

            RolePermissionHistory::query()->create([
                'subject_type' => 'team',
                'subject_id' => $team->id,
                'change_type' => $old === null ? 'team_created' : 'team_updated',
                'old_value' => $old,
                'new_value' => [
                    'name' => $team->name,
                    'manager_id' => $team->manager_id,
                    'member_ids' => collect($memberIds)->sort()->values()->all(),
                    'is_active' => $team->is_active,
                ],
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $team->refresh();
        });
    }
}
