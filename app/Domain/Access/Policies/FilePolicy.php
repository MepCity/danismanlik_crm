<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class FilePolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'document.upload';

    protected const VIEW_PERMISSION = 'document.download';

    public function download(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }
}
