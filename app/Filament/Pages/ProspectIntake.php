<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Crm\Actions\CreateProspectIntake;
use App\Domain\Crm\DTOs\ProspectIntakeData;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Services\CompanyDuplicateFinder;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\ProgramVersion;
use App\Support\Authorization\ScopedQuery;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ProspectIntake extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $slug = 'potansiyel-musteri-ekle';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.prospect-intake';

    public string $companyMode = 'new';

    public ?int $companyId = null;

    public string $companyName = '';

    public string $taxNumber = '';

    public string $city = '';

    public string $source = 'phone';

    public string $contactMode = 'new';

    public ?int $contactId = null;

    public string $contactName = '';

    public string $contactTitle = '';

    public string $decisionRole = '';

    public string $phone = '';

    public string $email = '';

    public string $callConsent = 'granted';

    public string $disclosureDate = '';

    public string $disclosureMethod = 'Telefon görüşmesi';

    public ?int $programVersionId = null;

    public ?int $targetStatusId = null;

    public string $calledAt = '';

    public string $callDirection = 'outbound';

    public ?int $durationMinutes = null;

    public string $outcome = 'interested';

    public string $callNote = '';

    public string $nextCallAt = '';

    public string $companyComment = '';

    public string $taskTitle = '';

    public string $taskDueAt = '';

    public string $taskRemindAt = '';

    public function mount(): void
    {
        $this->calledAt = now()->format('Y-m-d\TH:i');
        $this->disclosureDate = today()->toDateString();
        $this->targetStatusId = $this->statusOptions()->first()?->id;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('create', Lead::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.intake.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.marketing');
    }

    public function getTitle(): string
    {
        return __('marketing.intake.title');
    }

    public function updatedCompanyId(): void
    {
        $this->contactId = null;
        $this->contactMode = 'new';
    }

    public function save(CreateProspectIntake $intake): void
    {
        $newCompany = $this->companyMode === 'new';
        $newContact = $newCompany || $this->contactMode === 'new';
        $this->validate([
            'companyMode' => ['required', 'in:new,existing'],
            'companyId' => $newCompany ? ['nullable'] : ['required', 'integer'],
            'companyName' => $newCompany ? ['required', 'string', 'max:255'] : ['nullable'],
            'taxNumber' => ['nullable', 'regex:/^[0-9]{10}([0-9])?$/'],
            'city' => $newCompany ? ['required', 'regex:/^(0[1-9]|[1-7][0-9]|8[01])$/'] : ['nullable'],
            'source' => ['required', 'in:form,phone,list,referral,iys,other'],
            'contactId' => $newContact ? ['nullable'] : ['required', 'integer'],
            'contactName' => $newContact ? ['required', 'string', 'max:255'] : ['nullable'],
            'contactTitle' => $newContact ? ['required', 'string', 'max:255'] : ['nullable'],
            'decisionRole' => $newContact ? ['required', 'in:decision_maker,authorized_contact,technical_contact,financial_contact,information_provider,other'] : ['nullable'],
            'phone' => $newContact ? ['required', 'string', 'max:40'] : ['nullable'],
            'email' => $newContact ? ['required', 'email', 'max:255'] : ['nullable'],
            'callConsent' => $newContact ? ['required', 'in:unknown,granted,denied'] : ['nullable'],
            'programVersionId' => ['required', 'integer'],
            'targetStatusId' => ['required', 'integer'],
            'calledAt' => ['required', 'date'],
            'callDirection' => ['required', 'in:inbound,outbound'],
            'durationMinutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'callNote' => ['required', 'string', 'min:3', 'max:5000'],
            'nextCallAt' => $this->targetRequires('next_call_at') ? ['required', 'date', 'after:calledAt'] : ['nullable', 'date'],
            'companyComment' => ['nullable', 'string', 'max:10000'],
            'taskTitle' => ['nullable', 'string', 'max:255'],
            'taskDueAt' => filled($this->taskTitle) ? ['required', 'date', 'after_or_equal:calledAt'] : ['nullable', 'date'],
            'taskRemindAt' => ['nullable', 'date', 'before_or_equal:taskDueAt'],
        ]);

        $actor = Auth::user();
        abort_unless($actor !== null, 403);
        $result = $intake->handle($actor, new ProspectIntakeData(
            companyId: $newCompany ? null : $this->companyId,
            companyName: $newCompany ? $this->companyName : null,
            taxNumber: $newCompany && filled($this->taxNumber) ? $this->taxNumber : null,
            city: $newCompany ? $this->city : null,
            source: $this->source,
            contactId: $newContact ? null : $this->contactId,
            contactName: $newContact ? $this->contactName : null,
            contactTitle: $newContact ? $this->contactTitle : null,
            decisionRole: $newContact ? $this->decisionRole : null,
            phone: $newContact ? $this->phone : null,
            email: $newContact ? $this->email : null,
            callConsent: $newContact ? match ($this->callConsent) {
                'granted' => true, 'denied' => false, default => null
            } : null,
            disclosureDate: $newContact && filled($this->disclosureDate) ? $this->disclosureDate : null,
            disclosureMethod: $newContact && filled($this->disclosureMethod) ? $this->disclosureMethod : null,
            programVersionId: (int) $this->programVersionId,
            targetStatusId: (int) $this->targetStatusId,
            calledAt: Carbon::parse($this->calledAt),
            callDirection: $this->callDirection,
            durationMinutes: $this->durationMinutes,
            outcome: $this->outcome ?: null,
            callNote: $this->callNote,
            nextCallAt: filled($this->nextCallAt) ? Carbon::parse($this->nextCallAt) : null,
            companyComment: $this->companyComment ?: null,
            taskTitle: $this->taskTitle ?: null,
            taskDueAt: filled($this->taskDueAt) ? Carbon::parse($this->taskDueAt) : null,
            taskRemindAt: filled($this->taskRemindAt) ? Carbon::parse($this->taskRemindAt) : null,
        ));

        Notification::make()->title(__('marketing.intake.saved'))->success()->send();
        $this->redirect(LeadDetail::getUrl(['lead' => $result->lead->id]));
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $companies = app(ScopedQuery::class)->apply(Company::query(), $user)->orderBy('legal_name')->get();
        $selectedCompany = $this->companyId === null ? null : $companies->firstWhere('id', $this->companyId);

        return [
            'companies' => $companies,
            'contacts' => $selectedCompany?->contacts()->where('is_active', true)->orderBy('full_name')->get() ?? collect(),
            'programVersions' => ProgramVersion::query()->with('program')->where('is_active', true)->orderByDesc('application_opens_at')->orderByDesc('id')->get(),
            'statusOptions' => $this->statusOptions(),
            'selectedStatus' => $this->targetStatusId === null ? null : Status::query()->find($this->targetStatusId),
            'duplicateCompanies' => app(CompanyDuplicateFinder::class)->find($companies, $this->companyName, $this->taxNumber),
        ];
    }

    /** @return Collection<int, Status> */
    private function statusOptions(): Collection
    {
        $initialId = Status::query()->where('type', 'lead')->where('is_initial', true)->where('is_active', true)->value('id');
        if ($initialId === null) {
            return collect();
        }
        $ids = DB::table('transitions as first')
            ->leftJoin('transitions as second', function ($join): void {
                $join->on('second.from_status_id', '=', 'first.to_status_id')->where('second.is_active', true);
            })
            ->where('first.from_status_id', $initialId)
            ->where('first.is_active', true)
            ->selectRaw('first.to_status_id as direct_id, second.to_status_id as second_id')
            ->get()
            ->flatMap(static fn (object $row): array => [(int) $row->direct_id, $row->second_id === null ? null : (int) $row->second_id])
            ->filter()->unique()->values();

        return Status::query()->whereIn('id', $ids)->where('type', 'lead')->where('is_active', true)
            ->where('converts_to_deal', false)->orderBy('sort_order')->get();
    }

    private function targetRequires(string $field): bool
    {
        $status = $this->targetStatusId === null ? null : Status::query()->find($this->targetStatusId);

        return $status !== null && in_array($field, $status->required_fields, true);
    }
}
