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
            throw new InvalidArgumentException(__('management.validation.reason_required'));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(__('validation.email', ['attribute' => 'email']));
        }

        if (mb_strlen($password) < 12) {
            throw new InvalidArgumentException(__('validation.min.string', ['attribute' => 'password', 'min' => 12]));
        }

        $adminRole = Role::query()->where('name', 'Sistem Yöneticisi')->first();
        if ($adminRole === null) {
            throw new InvalidArgumentException('Sistem Yöneticisi rolü bulunamadı.');
        }

        if (! $force) {
            $existingAdmin = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Sistem Yöneticisi'))
                ->exists();

            if ($existingAdmin) {
                throw new InvalidArgumentException('Etkin bir Sistem Yöneticisi zaten mevcut.');
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
