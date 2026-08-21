<?php

declare(strict_types=1);

namespace App\Domain\Access\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CompanyPolicy extends ScopedPolicy
{
    protected const MUTATE_PERMISSION = 'company.manage';

    protected const VIEW_PERMISSION = 'company.manage';

    public function bulkEmail(User $user, Model $company): bool
    {
        return $user->hasPermissionTo('company.bulk_email') && $this->view($user, $company);
    }
}
