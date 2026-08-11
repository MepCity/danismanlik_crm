<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Models\User;
use App\Support\Authorization\PolicyDecision;
use Illuminate\Database\Eloquent\Model;

abstract class ScopedPolicy
{
    protected const CONFIGURATION = false;

    protected const MUTATE_PERMISSION = '';

    protected const VIEW_PERMISSION = '';

    public function __construct(private readonly PolicyDecision $decision) {}

    public function viewAny(User $user): bool
    {
        return $this->decision->viewAny($user, static::VIEW_PERMISSION, static::CONFIGURATION);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->decision->record($user, static::VIEW_PERMISSION, $model, static::CONFIGURATION);
    }

    public function create(User $user): bool
    {
        return $this->decision->create($user, static::MUTATE_PERMISSION, static::CONFIGURATION);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->decision->record($user, static::MUTATE_PERMISSION, $model, static::CONFIGURATION);
    }

    public function deactivate(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
