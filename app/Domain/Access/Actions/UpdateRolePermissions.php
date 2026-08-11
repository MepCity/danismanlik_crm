<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class UpdateRolePermissions
{
    /**
     * @param  list<int>  $permissionIds
     * @param  array<string, mixed>  $attributes
     */
    public function execute(Role $role, array $permissionIds, string $reason, User $actor, array $attributes = []): Role
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

        return DB::transaction(function () use ($role, $permissionIds, $reason, $actor, $attributes): Role {
            $old = $role->permissions()->pluck('permissions.id')->sort()->values()->all();
            $oldAttributes = $role->only(['default_scope', 'is_active']);
            $role->fill($attributes)->save();
            $role->syncPermissions($permissionIds);

            RolePermissionHistory::query()->create([
                'subject_type' => 'role',
                'subject_id' => (int) $role->id,
                'change_type' => 'permissions_updated',
                'old_value' => ['permission_ids' => $old, ...$oldAttributes],
                'new_value' => ['permission_ids' => $permissionIds, ...$role->only(['default_scope', 'is_active'])],
                'changed_by' => $actor->id,
                'reason' => $reason,
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->refresh();
        });
    }
}
