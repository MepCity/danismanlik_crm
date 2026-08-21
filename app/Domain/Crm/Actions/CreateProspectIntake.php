<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Collaboration\Services\TaskService;
use App\Domain\Crm\DTOs\ProspectIntakeData;
use App\Domain\Crm\DTOs\ProspectIntakeResult;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Services\ProspectWorkflowGateway;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateProspectIntake
{
    public function __construct(
        private SaveContact $contacts,
        private RecordInteraction $interactions,
        private TransitionLead $leadTransitions,
        private CommentService $comments,
        private TaskService $tasks,
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
        private ProspectWorkflowGateway $workflow,
    ) {}

    public function handle(User $actor, ProspectIntakeData $data): ProspectIntakeResult
    {
        Gate::forUser($actor)->authorize('create', Lead::class);
        Gate::forUser($actor)->authorize('create', Contact::class);
        $this->validate($data);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $data): ProspectIntakeResult {
            $company = $this->company($actor, $data);
            $contact = $this->contact($actor, $company, $data);
            $initial = $this->workflow->initialState();

            $lead = Lead::query()->create([
                'company_id' => $company->id,
                'primary_contact_id' => $contact->id,
                'owner_user_id' => $actor->id,
                'source' => $data->source,
                'interested_program_version_id' => $data->programVersionId,
                'status_id' => $initial->statusId,
            ]);
            $this->workflow->recordInitialHistory($lead->id, $initial, $actor->id);
            $this->activities->record(
                'lead.created',
                [
                    'company' => ['id' => $company->id, 'name' => $company->legal_name],
                    'contact' => ['id' => $contact->id, 'name' => $contact->full_name],
                    'program_version_id' => $data->programVersionId,
                    'initial_status' => ['id' => $initial->statusId, 'label' => $initial->statusLabel],
                ],
                $actor->id,
                leadId: $lead->id,
                defaultSource: 'user',
            );

            $interaction = $data->callDirection === 'inbound'
                ? $this->interactions->forInboundLeadCall($lead->id, $actor->id, $data->calledAt, $data->outcome, $data->callNote, $contact->id)
                : $this->interactions->forLead($lead->id, $actor->id, 'call', $data->calledAt, $data->outcome, $data->callNote, $contact->id);

            $path = $this->workflow->transitionPath($initial->statusId, $data->targetStatusId);
            foreach ($path as $step => $targetStatusId) {
                $isFinal = $step === array_key_last($path);
                $this->leadTransitions->handle(
                    $lead->id,
                    $targetStatusId,
                    $actor->id,
                    $isFinal ? $data->nextCallAt?->toDateTimeString() : null,
                    $actor->id,
                    programVersionId: $data->programVersionId,
                );
            }

            if (filled($data->companyComment)) {
                $this->comments->create($actor, new SubjectReference(CollaborationSubjectType::Company, $company->id), (string) $data->companyComment);
            }
            if (filled($data->taskTitle)) {
                $this->tasks->create(
                    $actor,
                    new SubjectReference(CollaborationSubjectType::Lead, $lead->id),
                    $actor,
                    (string) $data->taskTitle,
                    $data->taskDueAt,
                    $data->taskRemindAt,
                    $data->callNote,
                );
            }

            return new ProspectIntakeResult($company, $contact, $lead->refresh(), $interaction);
        });
    }

    private function company(User $actor, ProspectIntakeData $data): Company
    {
        if ($data->companyId !== null) {
            $company = Company::query()->findOrFail($data->companyId);
            Gate::forUser($actor)->authorize('view', $company);

            return $company;
        }

        Gate::forUser($actor)->authorize('create', Company::class);
        $company = Company::query()->create([
            'owner_user_id' => $actor->id,
            'legal_name' => trim((string) $data->companyName),
            'tax_number' => filled($data->taxNumber) ? trim((string) $data->taxNumber) : null,
            'industry' => $data->companyIndustry,
            'city' => $data->city,
            'source' => $data->source,
            'is_active' => true,
        ]);
        $this->activities->record(
            'company.created',
            ['company' => ['id' => $company->id, 'name' => $company->legal_name]],
            $actor->id,
            defaultSource: 'user',
            companyId: $company->id,
        );

        return $company;
    }

    private function contact(User $actor, Company $company, ProspectIntakeData $data): Contact
    {
        if ($data->contactId !== null) {
            $contact = Contact::query()->where('company_id', $company->id)->where('is_active', true)->findOrFail($data->contactId);
            Gate::forUser($actor)->authorize('view', $contact);

            return $contact;
        }

        $contact = $this->contacts->create(
            $company->id,
            $actor->id,
            (string) $data->contactName,
            phone: $data->phone,
            email: $data->email,
            title: $data->contactTitle,
            callConsent: $data->callConsent,
            disclosureDate: $data->disclosureDate,
            isPrimary: ! $company->contacts()->where('is_primary', true)->exists(),
            consentEffectiveAt: $data->calledAt,
            recordSource: $data->source,
        );
        $this->activities->record(
            'contact.created',
            [
                'contact' => ['id' => $contact->id, 'name' => $contact->full_name],
                'title' => $contact->title,
            ],
            $actor->id,
            defaultSource: 'user',
            companyId: $company->id,
        );

        return $contact;
    }

    private function validate(ProspectIntakeData $data): void
    {
        $errors = [];
        if ($data->companyId === null && (
            blank($data->companyName)
            || ! in_array($data->city, config('turkey.provinces'), true)
            || ! in_array($data->companyIndustry, config('operations.company_industries'), true)
        )) {
            $errors['company'] = trans('marketing.validation.company_required');
        }
        if ($data->contactId === null && (blank($data->contactName) || blank($data->contactTitle) || blank($data->phone) || blank($data->email))) {
            $errors['contact'] = trans('marketing.validation.contact_details_required');
        }
        if (! in_array($data->callDirection, ['inbound', 'outbound'], true)) {
            $errors['callDirection'] = trans('marketing.validation.call_direction');
        }
        if (mb_strlen(trim($data->callNote)) < 3) {
            $errors['callNote'] = trans('marketing.validation.call_note_required');
        }
        if (! $this->workflow->activeProgramVersionExists($data->programVersionId)) {
            $errors['programVersionId'] = trans('marketing.validation.program_version_id_required');
        }

        $initial = $this->workflow->initialState();
        if ($this->workflow->transitionPath($initial->statusId, $data->targetStatusId) === []) {
            $errors['targetStatusId'] = trans('marketing.validation.intake_status');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
