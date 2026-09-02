<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Filament\Pages\DealDetail;
use App\Filament\Pages\LeadDetail;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class NotificationUrlResolver
{
    public function resolve(User $user, Notification $notification): ?string
    {
        if ($notification->user_id !== $user->id) {
            return null;
        }

        if ($notification->deal_id !== null) {
            $deal = Deal::query()->find($notification->deal_id);
            if ($deal !== null && Gate::forUser($user)->allows('view', $deal)) {
                return DealDetail::getUrl(['deal' => $deal->id]);
            }
        }

        if ($notification->lead_id !== null) {
            $lead = Lead::query()->find($notification->lead_id);
            if ($lead !== null && Gate::forUser($user)->allows('view', $lead)) {
                return LeadDetail::getUrl(['lead' => $lead->id]);
            }
        }

        return null;
    }
}
