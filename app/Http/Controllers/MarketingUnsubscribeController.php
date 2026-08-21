<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Crm\Actions\WithdrawEmailConsent;
use App\Domain\Crm\Models\Contact;
use Illuminate\Contracts\View\View;

final class MarketingUnsubscribeController
{
    public function __invoke(Contact $contact, WithdrawEmailConsent $withdraw): View
    {
        $withdraw->execute($contact);

        return view('marketing-unsubscribed');
    }
}
