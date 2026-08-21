<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Services\PageAccess;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class SaveUser
{
    /** @param array<string, mixed> $data */
    public function execute(?User $user, array $data, User $actor): User
    {
        $reason = trim((string) Arr::pull($data, 'change_reason'));
        $roleIds = array_map('intval', (array) Arr::pull($data, 'role_ids', []));
        $teamIds = array_map('intval', (array) Arr::pull($data, 'team_ids', []));
        $pagePermissionIds = Arr::has($data, 'page_permission_ids')
            ? array_map('intval', (array) Arr::pull($data, 'page_permission_ids', []))
            : null;

        if ($reason === '') {
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

        return DB::transaction(function () use ($user, $data, $actor, $reason, $roleIds, $teamIds, $pagePermissionIds): User {
            $old = $user === null ? null : [
                'roles' => $user->roles()->pluck('roles.id')->sort()->values()->all(),
                'teams' => $user->teams()->pluck('teams.id')->sort()->values()->all(),
                'data_scope' => $user->data_scope,
                'is_active' => $user->is_active,
                'page_permissions' => $user->getDirectPermissions()->filter(static fn (Permission $permission): bool => str_starts_with($permission->name, 'page.'))->pluck('name')->sort()->values()->all(),
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

            if ($pagePermissionIds !== null) {
                $this->syncPagePermissions($user, $pagePermissionIds);
            }

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
                    'page_permissions' => $user->getDirectPermissions()->filter(static fn (Permission $permission): bool => str_starts_with($permission->name, 'page.'))->pluck('name')->sort()->values()->all(),
                ],
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $user->refresh();
        });
    }

    /** @param list<int> $permissionIds */
    private function syncPagePermissions(User $user, array $permissionIds): void
    {
        $pageAccess = app(PageAccess::class);
        $allowed = Permission::query()
            ->whereIn('name', $pageAccess->permissionNames())
            ->whereIn('id', $permissionIds)
            ->get();

        if ($allowed->count() !== count(array_unique($permissionIds))) {
            throw new InvalidArgumentException(__('management.validation.invalid_page_permission'));
        }

        $preserved = $user->getDirectPermissions()->reject(
            static fn (Permission $permission): bool => str_starts_with($permission->name, 'page.'),
        );
        $marker = Permission::findByName((string) config('access.page_management_permission'));
        $user->syncPermissions($preserved->push($marker)->concat($allowed));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
