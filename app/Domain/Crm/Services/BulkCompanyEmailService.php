<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Collaboration\Services\EmailNotificationService;
use App\Domain\Crm\DTOs\BulkEmailResult;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\EmailTemplate;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final readonly class BulkCompanyEmailService
{
    public function __construct(
        private EmailNotificationService $emails,
        private EmailTemplateRenderer $renderer,
        private MarketingUnsubscribeUrl $unsubscribeUrl,
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
    ) {}

    /**
     * @param  Collection<int, Company>  $companies
     * @param  array<string, mixed>  $filterSnapshot
     */
    public function send(Collection $companies, int $templateId, array $filterSnapshot, User $actor): BulkEmailResult
    {
        $validated = Validator::make(compact('templateId'), [
            'templateId' => ['required', 'integer', 'exists:email_templates,id'],
        ])->validate();
        $template = EmailTemplate::query()->whereKey($validated['templateId'])->where('is_active', true)->firstOrFail();

        return $this->sendContent(
            $companies,
            $template->subject,
            $template->body,
            $filterSnapshot,
            $actor,
            ['id' => $template->id, 'name' => $template->name, 'subject' => $template->subject],
        );
    }

    /**
     * @param  Collection<int, Company>  $companies
     * @param  array<string, mixed>  $filterSnapshot
     */
    public function sendComposed(Collection $companies, string $subject, string $body, array $filterSnapshot, User $actor): BulkEmailResult
    {
        $validated = Validator::make(compact('subject', 'body'), [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ])->validate();
        $this->renderer->validate($validated['subject'], $validated['body']);

        return $this->sendContent(
            $companies,
            $validated['subject'],
            $validated['body'],
            $filterSnapshot,
            $actor,
            ['id' => null, 'name' => trans('panel.company_directory.bulk_email.composed_source'), 'subject' => $validated['subject']],
        );
    }

    /**
     * @param  Collection<int, Company>  $companies
     * @param  array<string, mixed>  $filterSnapshot
     * @param  array{id: int|null, name: string, subject: string}  $contentSnapshot
     */
    private function sendContent(Collection $companies, string $subject, string $body, array $filterSnapshot, User $actor, array $contentSnapshot): BulkEmailResult
    {
        $companies->each(fn (Company $company) => Gate::forUser($actor)->authorize('bulkEmail', $company));

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($companies, $subject, $body, $filterSnapshot, $actor, $contentSnapshot): BulkEmailResult {
            $companyIds = $companies->modelKeys();
            $contacts = Contact::query()
                ->whereIn('company_id', $companyIds)
                ->where('is_active', true)
                ->with([
                    'company',
                    'communicationConsents' => fn ($query) => $query
                        ->where('channel', 'email')
                        ->where('purpose', 'marketing')
                        ->where('effective_from', '<=', now())
                        ->orderByDesc('effective_from')
                        ->orderByDesc('id'),
                ])
                ->get();

            $queued = 0;
            $rejected = 0;
            $missing = 0;
            $duplicates = 0;
            $seenEmails = [];

            foreach ($contacts as $contact) {
                if (blank($contact->email)) {
                    $missing++;

                    continue;
                }

                $latestConsent = $contact->communicationConsents->first();
                if ($contact->consent_email !== true || $latestConsent?->status !== 'granted') {
                    $rejected++;

                    continue;
                }

                $normalizedEmail = mb_strtolower(trim($contact->email));
                if (isset($seenEmails[$normalizedEmail])) {
                    $duplicates++;

                    continue;
                }
                $seenEmails[$normalizedEmail] = true;

                $rendered = $this->renderer->renderContent($subject, $body, $contact);
                $renderedBody = $rendered['body']."\n\n".trans('marketing.unsubscribe.line', [
                    'url' => $this->unsubscribeUrl->for($contact),
                ]);

                $this->emails->queueExternal(
                    $contact->email,
                    $contact->full_name,
                    'company.bulk_email',
                    $rendered['subject'],
                    $renderedBody,
                    new SubjectReference(CollaborationSubjectType::Company, $contact->company_id),
                );
                $queued++;
            }

            $payload = [
                'selected_company_count' => $companies->count(),
                'queued_recipient_count' => $queued,
                'consent_rejected_count' => $rejected,
                'missing_email_count' => $missing,
                'duplicate_count' => $duplicates,
                'filters' => $filterSnapshot,
                'template' => $contentSnapshot,
            ];

            $companies->each(fn (Company $company) => $this->activities->record(
                action: 'company.bulk_email_requested',
                payload: [...$payload, 'company' => ['id' => $company->id, 'name' => $company->legal_name]],
                actorId: $actor->id,
                defaultSource: 'user',
                companyId: $company->id,
            ));

            return new BulkEmailResult($queued, $rejected, $missing, $duplicates);
        });
    }
}
