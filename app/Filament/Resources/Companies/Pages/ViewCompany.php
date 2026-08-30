<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Collaboration\Services\TaskService;
use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Support\CompanyOpportunityAction;
use App\Filament\Support\CompanyWorkspaceSummary;
use App\Filament\Support\CustomerFlowAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /** Allowed content tabs. Anything else is rejected server-side. */
    public const TABS = ['activities', 'tasks', 'opportunities', 'files'];

    public string $activeTab = 'activities';

    public bool $showDetails = false;

    public string $activityFilter = 'comments';

    public string $activityDirection = 'desc';

    public function setActiveTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 422);

        $this->activeTab = $tab;
    }

    public function toggleDetails(): void
    {
        $this->showDetails = ! $this->showDetails;
    }

    private ?CompanyWorkspaceSummary $workspace = null;

    public function workspaceSummary(): CompanyWorkspaceSummary
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $this->workspace ??= CompanyWorkspaceSummary::for($this->company(), $user);
    }

    public function getTitle(): string
    {
        return $this->company()->legal_name;
    }

    public function getSubheading(): string
    {
        $company = $this->company();
        $summary = $this->workspaceSummary();

        return __('marketing.company.subtitle', [
            'contacts' => $company->contacts()->count(),
            'opportunities' => $summary->openLeads,
            'projects' => $summary->deals->count(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createTaskAction(),
            CustomerFlowAction::forRecord(),
            CompanyOpportunityAction::make(),
            EditAction::make()->label(__('panel.company_directory.edit')),
        ];
    }

    /**
     * Compact task modal on the identity card. Creation goes through TaskService,
     * so authorization, validation and activity recording stay in the domain.
     */
    private function createTaskAction(): Action
    {
        return Action::make('create_task')
            ->label(__('marketing.company.identity.create_task'))
            ->icon('heroicon-o-plus')
            ->color('success')
            ->modalWidth(Width::Medium)
            ->visible(fn (): bool => Gate::allows('create', Task::class))
            ->schema([
                TextInput::make('title')->label(__('collaboration.tasks.title'))->required()->maxLength(255),
                Select::make('assigned_to')
                    ->label(__('collaboration.tasks.assignee'))
                    ->options(fn (): array => $this->assignableUsers()->pluck('name', 'id')->all())
                    ->default(fn (): ?int => Auth::id())
                    ->required(),
                DateTimePicker::make('due_at')->label(__('collaboration.tasks.due_at'))->seconds(false),
                Textarea::make('description')->label(__('collaboration.tasks.description'))->rows(3),
            ])
            ->action(function (array $data, TaskService $tasks): void {
                $actor = Auth::user();
                abort_unless($actor instanceof User, 403);

                $assignee = $this->assignableUsers()->firstWhere('id', (int) $data['assigned_to']);
                abort_unless($assignee instanceof User, 422);

                $tasks->create(
                    $actor,
                    new SubjectReference(CollaborationSubjectType::Company, (int) $this->company()->getKey()),
                    $assignee,
                    (string) $data['title'],
                    filled($data['due_at'] ?? null) ? Carbon::parse((string) $data['due_at']) : null,
                    description: filled($data['description'] ?? null) ? (string) $data['description'] : null,
                );

                $this->activeTab = 'tasks';
                Notification::make()->title(__('collaboration.tasks.created_message'))->success()->send();
            });
    }

    /**
     * Candidates for a company task.
     *
     * The data scope alone is not an authorization answer: it says which rows a
     * user could reach, not whether the user may view companies at all. The
     * company policy evaluates the permission and the scope together, so an
     * active user whose scope covers the record but who lacks company.manage
     * never becomes assignable. TaskService keeps its own server-side check.
     *
     * @return Collection<int, User>
     */
    private function assignableUsers(): Collection
    {
        $company = $this->company();

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate): bool => Gate::forUser($candidate)->allows('view', $company))
            ->values();
    }

    /**
     * @return array<Action|ActionGroup>
     */
    public function getCachedHeaderActions(): array
    {
        return [];
    }

    public function setActivityFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['comments', 'history', 'all'], true), 422);

        $this->activityFilter = $filter;
    }

    public function setActivityDirection(string $direction): void
    {
        abort_unless(in_array($direction, ['asc', 'desc'], true), 422);

        $this->activityDirection = $direction;
    }

    public function toggleActivityDirection(): void
    {
        $this->activityDirection = $this->activityDirection === 'desc' ? 'asc' : 'desc';
    }

    private function company(): Company
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless($this->record instanceof Company, 404);
        // policy + data scope together, never the scope on its own
        abort_unless(Gate::forUser($user)->allows('view', $this->record), 403);

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
