<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Services\PageAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class BootstrapAdmin
{
    /** @param array<string, mixed> $data */
    public function execute(array $data, bool $force = false): User
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException(__('management.validation.name_required'));
        }

        if ($email === '') {
            throw new InvalidArgumentException(__('management.validation.email_required'));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(__('management.validation.email_invalid'));
        }

        if (
            mb_strlen($password) < 12
            || ! preg_match('/[A-Z]/', $password)
            || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/[0-9]/', $password)
            || ! preg_match('/[\W_]/', $password)
        ) {
            throw new InvalidArgumentException(__('management.validation.password_strong'));
        }

        $adminRole = Role::query()->where('name', 'Sistem Yöneticisi')->first();
        if ($adminRole === null) {
            throw new InvalidArgumentException(__('management.validation.admin_role_missing'));
        }

        if (! $force) {
            $existingAdmin = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Sistem Yöneticisi'))
                ->exists();

            if ($existingAdmin) {
                throw new InvalidArgumentException(__('management.validation.admin_exists'));
            }
        }

        return DB::transaction(function () use ($name, $email, $password, $adminRole): User {
            $user = User::query()->where('email', $email)->first() ?? new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->data_scope = 'none';
            $user->is_active = true;
            $user->deactivated_at = null;
            $user->save();

            $user->roles()->sync([(int) $adminRole->id]);

            $pageAccess = app(PageAccess::class);
            $pagePermissions = Permission::query()
                ->whereIn('name', $pageAccess->permissionNames())
                ->where('guard_name', 'web')
                ->get();
            $marker = Permission::query()
                ->where('name', (string) config('access.page_management_permission'))
                ->where('guard_name', 'web')
                ->first();

            $permissionsToSync = $pagePermissions;
            if ($marker !== null) {
                $permissionsToSync = $permissionsToSync->push($marker);
            }
            $user->syncPermissions($permissionsToSync);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            RolePermissionHistory::query()->create([
                'subject_type' => 'user',
                'subject_id' => $user->id,
                'change_type' => 'bootstrap_admin_created',
                'old_value' => null,
                'new_value' => [
                    'roles' => [$adminRole->id],
                    'data_scope' => 'none',
                    'is_active' => true,
                    'page_permissions' => $pagePermissions->pluck('name')->sort()->values()->all(),
                ],
                'changed_by' => $user->id,
                'reason' => 'İlk sistem yöneticisi kurulumu',
            ]);

            return $user->refresh();
        });
    }
}
