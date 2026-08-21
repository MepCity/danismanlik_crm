<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Support\CustomerFlowAction;
use Filament\Actions\EditAction;
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

    public string $contactEmailConsent = 'unknown';

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

    protected function getHeaderActions(): array
    {
        return [
            CustomerFlowAction::forRecord(),
            EditAction::make()->label(__('panel.company_directory.edit')),
        ];
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
            'contactEmailConsent' => ['required', 'in:unknown,granted,denied'],
            'contactDisclosureDate' => ['nullable', 'date'],
        ]);
        Gate::authorize('create', Contact::class);
        $emailConsent = match ($this->contactEmailConsent) {
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
            emailConsent: $emailConsent,
            disclosureDate: $this->contactDisclosureDate ?: null,
        );
        $this->reset('contactFullName', 'contactTitle', 'contactPhone', 'contactEmail', 'contactDisclosureDate');
        $this->contactEmailConsent = 'unknown';
        Notification::make()->title(__('marketing.messages.contact_saved'))->success()->send();
    }
}
