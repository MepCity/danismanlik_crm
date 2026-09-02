<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Rules\StrongPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UpsertPilotUser
{
    /**
     * @param  list<string>  $directPermissions
     */
    public function execute(
        string $email,
        string $password,
        string $name,
        string $roleName,
        string $dataScope,
        array $directPermissions = [],
    ): User {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(__('management.staging_provision.invalid_email', ['field' => $email]));
        }

        if (! StrongPassword::isValid($password)) {
            throw ValidationException::withMessages([
                $email => __('management.validation.password_strong'),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->email = $email;
            $user->name = $name;
            $user->password = Hash::make($password);
            $user->data_scope = $dataScope;
            $user->is_active = true;
            $user->save();

            $user->syncRoles([$roleName]);

            if (! empty($directPermissions)) {
                $user->givePermissionTo($directPermissions);
            }

            return $user;
        }

        // Parola doğrulaması — aynıysa yeniden hashleyip updated_at tetikleme
        if (! Hash::check($password, $user->password)) {
            $user->password = Hash::make($password);
        }

        if ($user->name !== $name) {
            $user->name = $name;
        }

        if ($user->data_scope !== $dataScope) {
            $user->data_scope = $dataScope;
        }

        if (! $user->is_active) {
            $user->is_active = true;
            $user->deactivated_at = null;
        }

        if ($user->isDirty()) {
            $user->save();
        }

        // Rol ataması — zaten bu role sahipse ve tek rolü buysa sync çağırma
        $currentRoles = $user->roles()->pluck('name')->all();
        if ($currentRoles !== [$roleName]) {
            $user->syncRoles([$roleName]);
        }

        // Doğrudan izinler — zaten atanmışsa tekrar verme
        foreach ($directPermissions as $perm) {
            if (! $user->hasDirectPermission($perm)) {
                $user->givePermissionTo($perm);
            }
        }

        return $user;
    }
}
