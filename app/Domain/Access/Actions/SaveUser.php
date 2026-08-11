<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class SaveUser
{
    /** @param array<string, mixed> $data */
    public function execute(?User $user, array $data, User $actor): User
    {
        $reason = trim((string) Arr::pull($data, 'change_reason'));
        $roleIds = array_map('intval', (array) Arr::pull($data, 'role_ids', []));
        $teamIds = array_map('intval', (array) Arr::pull($data, 'team_ids', []));

        if ($reason === '') {
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

        return DB::transaction(function () use ($user, $data, $actor, $reason, $roleIds, $teamIds): User {
            $old = $user === null ? null : [
                'roles' => $user->roles()->pluck('roles.id')->sort()->values()->all(),
                'teams' => $user->teams()->pluck('teams.id')->sort()->values()->all(),
                'data_scope' => $user->data_scope,
                'is_active' => $user->is_active,
            ];

            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make((string) $data['password']);
            }

            $wasActive = $user === null ? false : $user->is_active;
            $user ??= new User;
            $user->fill($data);
            $user->deactivated_at = $user->is_active ? null : now();
            $user->save();
            $user->roles()->sync($roleIds);
            $user->teams()->sync(collect($teamIds)->mapWithKeys(
                static fn (int $teamId): array => [$teamId => ['role' => 'member']],
            )->all());

            if ($wasActive && ! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            RolePermissionHistory::query()->create([
                'subject_type' => 'user',
                'subject_id' => $user->id,
                'change_type' => $old === null ? 'user_created' : 'user_access_updated',
                'old_value' => $old,
                'new_value' => [
                    'roles' => $roleIds,
                    'teams' => $teamIds,
                    'data_scope' => $user->data_scope,
                    'is_active' => $user->is_active,
                ],
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $user->refresh();
        });
    }
}
