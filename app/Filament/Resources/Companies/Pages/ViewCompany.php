<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Actions\WithdrawCallConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected string $view = 'filament.resources.companies.view-company';

    public string $contactFullName = '';

    public string $contactTitle = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public string $contactCallConsent = 'unknown';

    public string $contactDisclosureDate = '';

    public string $activeTab = 'overview';

    public function getTitle(): string
    {
        return $this->company()->legal_name;
    }

    public function getSubheading(): string
    {
        $company = $this->company();

        return __('marketing.company.subtitle', [
            'contacts' => $company->contacts()->count(),
            'opportunities' => $company->leads()->whereNull('converted_deal_id')->count(),
            'projects' => $company->deals()->count(),
        ]);
    }

    private function company(): Company
    {
        abort_unless($this->record instanceof Company, 404);

        return $this->record;
    }

    public function addContact(SaveContact $contacts): void
    {
        $this->validate([
            'contactFullName' => ['required', 'string', 'max:255'],
            'contactTitle' => ['nullable', 'string', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:40'],
            'contactEmail' => ['nullable', 'email', 'max:255'],
            'contactCallConsent' => ['required', 'in:unknown,granted,denied'],
            'contactDisclosureDate' => ['nullable', 'date'],
        ]);
        Gate::authorize('create', Contact::class);
        $consent = match ($this->contactCallConsent) {
            'granted' => true,
            'denied' => false,
            default => null,
        };
        $contacts->create(
            (int) $this->record->getKey(),
            (int) Auth::id(),
            $this->contactFullName,
            phone: $this->contactPhone ?: null,
            email: $this->contactEmail ?: null,
            title: $this->contactTitle ?: null,
            callConsent: $consent,
            disclosureDate: $this->contactDisclosureDate ?: null,
        );
        $this->reset('contactFullName', 'contactTitle', 'contactPhone', 'contactEmail', 'contactDisclosureDate');
        $this->contactCallConsent = 'unknown';
        Notification::make()->title(__('marketing.messages.contact_saved'))->success()->send();
    }

    public function doNotCall(int $contactId, WithdrawCallConsent $consents): void
    {
        $contact = Contact::query()->findOrFail($contactId);
        abort_unless($contact->company_id === (int) $this->record->getKey(), 404);
        Gate::authorize('update', $contact);
        $consents->handle($contact->id, (int) Auth::id());
        Notification::make()->title(__('marketing.messages.do_not_call_saved'))->success()->send();
    }
}
