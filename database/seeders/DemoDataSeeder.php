<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Document\Services\DocumentStatusService;
use App\Domain\Document\Services\DocumentUploadService;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'admin';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException((string) trans('seeders.demo_production_forbidden'));
        }

        $this->call(ReferenceDataSeeder::class);

        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $this->seedTeams($users);
            $this->seedBusinessData($users);
        });
    }

    /** @return array<string, User> */
    private function seedUsers(): array
    {
        $definitions = [
            'marketing' => ['Pazarlama', 'pazarlama@bizlife', ['Pazarlama'], 'own'],
            'project_manager' => ['Proje Yöneticisi', 'proje@bizlife', ['Proje Yöneticisi'], 'team'],
            'company_authority' => ['Yönetici', 'admin@bizlife', ['Şirket Yetkilisi', 'Sistem Yöneticisi'], 'all'],
        ];
        $users = [];

        foreach ($definitions as $key => [$name, $email, $roles, $dataScope]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true, 'data_scope' => $dataScope],
            );
            $user->forceFill(['data_scope' => $dataScope])->save();
            $user->syncRoles($roles);
            $users[$key] = $user;
        }

        return $users;
    }

    /** @param array<string, User> $users */
    private function seedTeams(array $users): void
    {
        $operations = Team::query()->updateOrCreate(
            ['name' => 'Demo Operasyon Takımı'],
            ['manager_id' => $users['project_manager']->id, 'is_active' => true],
        );
        $applications = Team::query()->updateOrCreate(
            ['name' => 'Demo Başvuru Takımı'],
            ['manager_id' => $users['project_manager']->id, 'is_active' => true],
        );

        foreach ([
            [$operations, $users['project_manager'], 'manager'],
            [$operations, $users['marketing'], 'member'],
            [$applications, $users['project_manager'], 'manager'],
            [$applications, $users['company_authority'], 'member'],
        ] as [$team, $user, $role]) {
            TeamMember::query()->updateOrCreate(
                ['team_id' => $team->id, 'user_id' => $user->id],
                ['role' => $role],
            );
        }
    }

    /** @param array<string, User> $users */
    private function seedBusinessData(array $users): void
    {
        $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
        $version = $program->versions()->where('call_period', '2026 çağrısı')->firstOrFail();
        $revision = WorkflowRevision::query()->where('reason', 'ilk kurulum')->firstOrFail();

        $companies = [
            Company::query()->updateOrCreate(
                ['legal_name' => 'Kurgusal Ufuk Teknoloji Ltd. Şti.'],
                ['tax_number' => null, 'industry' => 'manufacturing', 'city' => 'Hatay', 'size' => 'medium', 'source' => 'demo', 'is_active' => true],
            ),
            Company::query()->updateOrCreate(
                ['legal_name' => 'Kurgusal Mavi Üretim A.Ş.'],
                ['tax_number' => null, 'industry' => 'machinery', 'city' => 'Ankara', 'size' => 'small', 'source' => 'demo', 'is_active' => true],
            ),
            Company::query()->updateOrCreate(
                ['legal_name' => 'Kurgusal Pusula Sanayi Ltd. Şti.'],
                ['tax_number' => null, 'industry' => 'energy', 'city' => 'İstanbul', 'size' => 'micro', 'source' => 'demo', 'is_active' => true],
            ),
        ];

        $this->seedDirectoryCompanies();

        foreach ($companies as $index => $company) {
            $contact = Contact::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => 'yetkili'.($index + 1).'@firma.invalid'],
                [
                    'full_name' => 'Kurgusal Yetkili '.($index + 1),
                    'title' => 'Demo Yetkilisi',
                    'phone' => '+90 000 000 00 0'.($index + 1),
                    'data_source' => $index === 1 ? 'referral' : 'form',
                    'is_primary' => true,
                    'is_active' => true,
                    'consent_email' => true,
                ],
            );
            CommunicationConsent::query()->firstOrCreate(
                ['contact_id' => $contact->id, 'channel' => 'email', 'purpose' => 'marketing'],
                [
                    'status' => 'granted',
                    'legal_basis' => 'explicit_consent',
                    'source' => $index === 1 ? 'referral' : 'form',
                    'disclosure_date' => now()->subDays(20 + $index)->toDateString(),
                    'effective_from' => now()->subDays(10 + $index),
                    'recorded_by' => $users['marketing']->id,
                ],
            );
        }

        $leadStatuses = Status::query()->where('type', 'lead')->get()->keyBy('code');
        foreach ([
            [$companies[0], 'interested', now()->subDay()->setTime(10, 0)],
            [$companies[1], 'proposal_sent', now()->setTime(13, 30)],
            [$companies[2], 'callback', now()->subDays(3)->setTime(9, 15)],
        ] as $index => [$company, $statusCode, $nextCallAt]) {
            $lead = Lead::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'owner_user_id' => $users['marketing']->id,
                    'interested_program_version_id' => $version->id,
                ],
                [
                    'primary_contact_id' => $company->contacts()->where('is_primary', true)->value('id'),
                    'source' => 'demo',
                    'status_id' => $leadStatuses[$statusCode]->id,
                    'next_call_at' => $nextCallAt,
                ],
            );
            StatusHistory::query()->firstOrCreate(
                ['lead_id' => $lead->id, 'exited_at' => null],
                [
                    'status_id' => $leadStatuses[$statusCode]->id,
                    'status_label_snapshot' => $leadStatuses[$statusCode]->label,
                    'workflow_revision_id' => $revision->id,
                    'entered_at' => now()->subDays(6 - $index),
                    'changed_by' => $users['marketing']->id,
                    'reason' => 'demo veri kurulumu',
                ],
            );
            Interaction::query()->firstOrCreate(
                ['lead_id' => $lead->id, 'occurred_at' => now()->subDays(4 - $index)->setTime(11, 0)],
                [
                    'contact_id' => $company->contacts()->where('is_primary', true)->value('id'),
                    'user_id' => $users['marketing']->id,
                    'type' => 'call',
                    'direction' => 'outbound',
                    'purpose' => 'marketing',
                    'outcome' => $index === 0 ? 'contacted' : 'unreachable',
                    'note' => 'Kurgusal demo görüşme notu.',
                ],
            );
        }

        $dealStatuses = Status::query()->where('type', 'deal')->get()->keyBy('code');
        $dealDefinitions = [
            [$companies[0], 'DEMO-2026-001', 'collecting_documents', $users['project_manager'], '6500000.00', 1],
            [$companies[1], 'DEMO-2026-002', 'preparing_application', $users['project_manager'], '5400000.00', 2],
            [$companies[2], 'DEMO-2026-003', 'awaiting_assignment', null, '950000.00', 0],
            [$companies[0], 'DEMO-2026-004', 'collecting_documents', $users['project_manager'], '1750000.00', 1],
        ];

        foreach ($dealDefinitions as [$company, $reference, $statusCode, $projectManager, $amount, $completedStepCount]) {
            $workflowSnapshot = $this->workflowSnapshot($version->workflow_snapshot, $completedStepCount);
            $deal = Deal::query()->where('reference_no', $reference)->first();

            if ($deal === null) {
                $created = app(ChecklistDealGateway::class)->createAwaitingAssignment(
                    $company->id,
                    $version->id,
                    $workflowSnapshot,
                    $users['marketing']->id,
                    $reference,
                    'demo veri kurulumu',
                    $amount,
                );
                $deal = Deal::query()->findOrFail($created->id);
            } else {
                app(ChecklistDealGateway::class)->attachWorkflowSnapshotIfMissing($deal->id, $workflowSnapshot);
                $deal->update(['requested_amount' => $amount, 'priority' => 'normal']);
                $deal->refresh();
            }

            app(ChecklistGeneratorContract::class)->generate($deal->id, $users['marketing']->id);
            $this->advanceDeal($deal, (int) $dealStatuses[$statusCode]->id, $projectManager, $users, $dealStatuses);

            $wonStatus = $leadStatuses->first(fn (Status $status): bool => $status->converts_to_deal);
            $originatingLead = Lead::query()->updateOrCreate(
                ['source' => 'demo-sale-'.$reference],
                [
                    'company_id' => $company->id,
                    'primary_contact_id' => $company->contacts()->where('is_primary', true)->value('id'),
                    'owner_user_id' => $users['marketing']->id,
                    'interested_program_version_id' => $version->id,
                    'status_id' => $wonStatus->id,
                    'converted_deal_id' => $deal->id,
                ],
            );
            StatusHistory::query()->firstOrCreate(
                ['lead_id' => $originatingLead->id, 'exited_at' => null],
                [
                    'status_id' => $wonStatus->id,
                    'status_label_snapshot' => $wonStatus->label,
                    'workflow_revision_id' => $revision->id,
                    'entered_at' => now()->subDays(5),
                    'changed_by' => $users['marketing']->id,
                    'reason' => 'Kurgusal demo satış görüşmesi sonucunda iş alındı.',
                ],
            );
            Interaction::query()->firstOrCreate(
                ['lead_id' => $originatingLead->id, 'occurred_at' => now()->subDays(5)->setTime(14, 0)],
                [
                    'contact_id' => $originatingLead->primary_contact_id,
                    'user_id' => $users['marketing']->id,
                    'type' => 'call',
                    'direction' => 'outbound',
                    'purpose' => 'marketing',
                    'outcome' => 'interested',
                    'note' => 'Kurgusal satış görüşmesinde program kapsamı, hizmet ve sonraki adımlar üzerinde anlaşıldı.',
                ],
            );

            if ($reference === 'DEMO-2026-001') {
                $this->seedCollaborationDemo($deal, $users['marketing'], $users['project_manager']);
            }

            if ($reference === 'DEMO-2026-002') {
                $document = $deal->documents()->where('name_snapshot', 'Fizibilite Raporu')->firstOrFail();
                DocumentRequirementSuggestion::query()->firstOrCreate(
                    ['deal_document_id' => $document->id, 'status' => 'pending'],
                    [
                        'reason' => 'condition_no_longer_matches',
                        'reason_parameters' => ['document' => $document->name_snapshot],
                    ],
                );
            }
        }
    }

    private function seedDirectoryCompanies(): void
    {
        $industries = ['food', 'manufacturing', 'metal', 'machinery', 'textile', 'energy'];
        $cities = ['Adana', 'Ankara', 'Bursa', 'Gaziantep', 'İstanbul', 'İzmir'];
        $sizes = ['micro', 'small', 'medium'];

        foreach (range(1, 27) as $index) {
            Company::query()->updateOrCreate(
                ['legal_name' => sprintf('Kurgusal Rehber Firması %02d Ltd. Şti.', $index)],
                [
                    'tax_number' => null,
                    'industry' => $industries[($index - 1) % count($industries)],
                    'city' => $cities[($index - 1) % count($cities)],
                    'size' => $sizes[($index - 1) % count($sizes)],
                    'source' => 'demo',
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>
     */
    private function workflowSnapshot(?array $snapshot, int $completedStepCount): array
    {
        if ($snapshot === null || ! is_array($snapshot['steps'] ?? null) || $snapshot['steps'] === []) {
            throw new RuntimeException('Kurgusal demo hizmet rehberi oluşturulamadı.');
        }

        $snapshot['steps'] = collect($snapshot['steps'])
            ->values()
            ->map(static fn (array $step, int $index): array => [
                ...$step,
                'is_completed' => $index < $completedStepCount,
            ])
            ->all();

        return $snapshot;
    }

    /**
     * @param  array<string, User>  $users
     * @param  Collection<string, Status>  $dealStatuses
     */
    private function advanceDeal(Deal $deal, int $targetStatusId, ?User $projectManager, array $users, Collection $dealStatuses): void
    {
        $initialStatusId = (int) $dealStatuses['awaiting_assignment']->id;
        $assignedStatusId = (int) $dealStatuses['pm_assigned']->id;
        $collectingStatusId = (int) $dealStatuses['collecting_documents']->id;
        $preparingStatusId = (int) $dealStatuses['preparing_application']->id;

        if ($projectManager !== null && $deal->status_id === $initialStatusId) {
            $deal = app(AssignDeal::class)->handle(
                $deal->id,
                $projectManager->id,
                $assignedStatusId,
                $users['company_authority']->id,
            );
        }

        if ($projectManager !== null && $deal->status_id === $assignedStatusId) {
            app(StatusMachineContract::class)->transition(new StatusTransition(
                SubjectType::Deal,
                $deal->id,
                $collectingStatusId,
                $projectManager->id,
                'Kurgusal demo evrak toplama aşamasına geçti.',
            ));
            $deal->refresh();
        }

        $this->seedDealDocuments($deal, $targetStatusId === $preparingStatusId, $projectManager);

        if ($targetStatusId === $preparingStatusId && $deal->status_id === $collectingStatusId) {
            app(StatusMachineContract::class)->transition(new StatusTransition(
                SubjectType::Deal,
                $deal->id,
                $preparingStatusId,
                $projectManager->id,
                'Kurgusal demo başvuru hazırlama aşamasına geçti.',
            ));
        }
    }

    private function seedCollaborationDemo(Deal $deal, User $marketing, User $projectManager): void
    {
        $subject = new SubjectReference(CollaborationSubjectType::Deal, $deal->id);
        $comments = app(CommentService::class);
        $internal = Comment::query()->where('deal_id', $deal->id)->where('body', 'Firma eksik imza sayfasını yarın iletecek.')->first();

        if ($internal === null) {
            $internal = $comments->create($projectManager, $subject, 'Firma eksik imza sayfasını yarın iletecek.');
            $comments->create($marketing, $subject, 'Takip planına eklendi; yarın yeniden kontrol edeceğim.', $internal->id);
        }

        if (! Comment::query()->where('deal_id', $deal->id)->where('body', 'Başvuru evraklarınızı incelemeye aldık.')->exists()) {
            $comments->create($projectManager, $subject, 'Başvuru evraklarınızı incelemeye aldık.');
        }

        Activity::query()->firstOrCreate(
            ['deal_id' => $deal->id, 'action' => 'deal.status_changed', 'source' => 'user'],
            [
                'actor_id' => $marketing->id,
                'payload' => [
                    'from_status' => ['label' => 'PM atandı'],
                    'to_status' => ['label' => 'Belgeler toplanıyor'],
                ],
            ],
        );
        Activity::query()->firstOrCreate(
            ['deal_id' => $deal->id, 'action' => 'deal.condition_documents_added', 'source' => 'automation'],
            ['payload' => ['document_names' => ['Fizibilite Raporu']]],
        );
    }

    private function seedDealDocuments(Deal $deal, bool $acceptAll, ?User $projectManager): void
    {
        $documents = $deal->documents()->orderBy('id')->get();

        foreach ($documents as $index => $document) {
            if ($projectManager === null) {
                continue;
            }

            if ($acceptAll) {
                $this->seedDocumentFlow($document, $projectManager, 'accepted');

                continue;
            }

            match ($index) {
                0 => $this->seedDocumentFlow($document, $projectManager, 'accepted', 2),
                1 => $this->seedDocumentFlow($document, $projectManager, 'uploaded'),
                2 => $this->seedDocumentFlow($document, $projectManager, 'rejected'),
                default => null,
            };
        }
    }

    private function seedDocumentFlow(
        DealDocument $document,
        User $actor,
        string $target,
        int $versions = 1,
    ): void {
        $uploads = app(DocumentUploadService::class);

        while ($document->files()->count() < $versions) {
            $version = $document->files()->count() + 1;
            $temporaryPath = tempnam(sys_get_temp_dir(), 'bizlife-demo-');

            if ($temporaryPath === false) {
                throw new RuntimeException('Kurgusal demo PDF dosyası oluşturulamadı.');
            }

            try {
                file_put_contents($temporaryPath, $this->fictionalPdf($document, $version));
                $upload = new UploadedFile(
                    $temporaryPath,
                    'kurgusal-'.str($document->name_snapshot)->slug()."-surum-{$version}.pdf",
                    'application/pdf',
                    null,
                    true,
                );
                $uploads->upload($document->id, $upload, $actor->id);
            } finally {
                @unlink($temporaryPath);
            }
        }

        $document->refresh();

        if ($target === 'uploaded' || $document->status === $target) {
            return;
        }

        $statuses = app(DocumentStatusService::class);
        $statuses->startReview($document->id, $actor->id);
        $statuses->decide(
            $document->id,
            $target,
            $target === 'rejected' ? 'Kurgusal örnekte imza sayfası eksik bırakıldı.' : null,
            $actor->id,
        );
    }

    private function fictionalPdf(DealDocument $document, int $version): string
    {
        return "%PDF-1.4\n"
            ."% KURGUSAL DEMO BELGESI - gercek kisi veya firma verisi icermez\n"
            ."% Evrak: {$document->name_snapshot}\n"
            ."% Surum: {$version}\n"
            ."%%EOF\n";
    }
}
