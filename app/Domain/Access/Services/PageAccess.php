<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class PageAccess
{
    public function allows(User $user, string $screenClass): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $definition = $this->definitionFor($screenClass);
        if ($definition === null) {
            return false;
        }

        $managementPermission = (string) config('access.page_management_permission');
        if ($user->hasDirectPermission($managementPermission)) {
            return $user->hasDirectPermission($definition['permission']);
        }

        return $user->can($definition['fallback']);
    }

    /** @param list<int> $roleIds
     * @return list<int>
     */
    public function presetForRoles(array $roleIds): array
    {
        $permissionNames = Role::query()
            ->whereIn('id', array_map('intval', $roleIds))
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique();

        $pageNames = collect($this->definitions())
            ->filter(fn (array $definition): bool => $permissionNames->contains($definition['fallback']))
            ->keys();

        return Permission::query()
            ->whereIn('name', $pageNames)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<int, string> */
    public function options(): array
    {
        $labels = collect($this->definitions())->map(fn (array $definition): string => trans($definition['label']));

        return Permission::query()
            ->whereIn('name', $labels->keys())
            ->get()
            ->mapWithKeys(fn ($permission): array => [$permission->id => $labels->get($permission->name, $permission->name)])
            ->all();
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        return array_keys($this->definitions());
    }

    /** @return array{permission: string, fallback: string}|null */
    private function definitionFor(string $screenClass): ?array
    {
        foreach ($this->definitions() as $permission => $definition) {
            if (in_array($screenClass, $definition['classes'], true)) {
                return ['permission' => $permission, 'fallback' => $definition['fallback']];
            }
        }

        return null;
    }

    /** @return array<string, array{label: string, fallback: string, classes: list<class-string>}> */
    private function definitions(): array
    {
        /** @var array<string, array{label: string, fallback: string, classes: list<class-string>}> $definitions */
        $definitions = config('access.pages', []);

        return $definitions;
    }
}
