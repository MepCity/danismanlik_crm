<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Domain\Document\Models\DealDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class DealDocumentPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'document.upload';

    protected const VIEW_PERMISSION = 'document.view';

    public function view(User $user, Model $model): bool
    {
        if (parent::view($user, $model)) {
            return true;
        }

        return $model instanceof DealDocument
            && Gate::forUser($user)->allows('view', $model->deal);
    }
}
