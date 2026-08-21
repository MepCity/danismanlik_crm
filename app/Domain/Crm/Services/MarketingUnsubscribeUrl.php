<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Contact;
use Illuminate\Support\Facades\URL;

final class MarketingUnsubscribeUrl
{
    public function for(Contact $contact): string
    {
        return URL::temporarySignedRoute(
            'marketing.unsubscribe',
            now()->addDays((int) config('operations.marketing_unsubscribe_days', 30)),
            ['contact' => $contact->id],
        );
    }
}
