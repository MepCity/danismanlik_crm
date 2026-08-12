<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Document\Services\DocumentStatusService;
use App\Domain\Document\Services\DocumentUploadService;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo123!';

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
            'marketing' => ['Demo Pazarlama Kullanıcısı', 'pazarlama@demo.invalid', 'Pazarlama'],
            'project_manager' => ['Demo Proje Yöneticisi', 'proje.yoneticisi@demo.invalid', 'Proje Yöneticisi'],
            'company_authority' => ['Demo Şirket Yetkilisi', 'sirket.yetkilisi@demo.invalid', 'Şirket Yetkilisi'],
            'system_admin' => ['Demo Sistem Yöneticisi', 'sistem.yoneticisi@demo.invalid', 'Sistem Yöneticisi'],
            'second_project_manager' => ['Demo İkinci Proje Yöneticisi', 'ikinci.proje.yoneticisi@demo.invalid', 'Proje Yöneticisi'],
        ];
        $users = [];

        foreach ($definitions as $key => [$name, $email, $role]) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true],
            );
            $user->syncRoles([$role]);
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
            ['manager_id' => $users['second_project_manager']->id, 'is_active' => true],
        );

        foreach ([
            [$operations, $users['project_manager'], 'manager'],
            [$operations, $users['marketing'], 'member'],
            [$applications, $users['second_project_manager'], 'manager'],
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
                ['tax_number' => null, 'city' => '31', 'size' => 'medium', 'source' => 'demo', 'is_active' => true],
            ),
            Company::query()->updateOrCreate(
                ['legal_name' => 'Kurgusal Mavi Üretim A.Ş.'],
                ['tax_number' => null, 'city' => '06', 'size' => 'small', 'source' => 'demo', 'is_active' => true],
            ),
            Company::query()->updateOrCreate(
                ['legal_name' => 'Kurgusal Pusula Sanayi Ltd. Şti.'],
                ['tax_number' => null, 'city' => '34', 'size' => 'micro', 'source' => 'demo', 'is_active' => true],
            ),
        ];

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
                    'consent_call' => $index !== 2,
                    'consent_email' => true,
                    'do_not_call' => $index === 2,
                ],
            );
            CommunicationConsent::query()->firstOrCreate(
                ['contact_id' => $contact->id, 'channel' => 'call', 'purpose' => 'marketing'],
                [
                    'status' => $index === 2 ? 'withdrawn' : 'granted',
                    'legal_basis' => $index === 2 ? 'explicit_withdrawal' : 'explicit_consent',
                    'source' => $index === 1 ? 'referral' : 'form',
                    'disclosure_date' => now()->subDays(20 + $index)->toDateString(),
                    'disclosure_method' => $index === 1 ? 'phone' : 'form',
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
                    'user_id' => $users['marketing']->id,
                    'type' => 'call',
                    'direction' => 'outbound',
                    'purpose' => 'marketing',
                    'duration_minutes' => 3 + $index,
                    'outcome' => $index === 0 ? 'contacted' : 'unreachable',
                    'note' => 'Kurgusal demo görüşme notu.',
                ],
            );
        }

        $dealStatuses = Status::query()->where('type', 'deal')->get()->keyBy('code');
        $dealDefinitions = [
            [$companies[0], 'DEMO-2026-001', 'collecting_documents', $users['project_manager'], '6500000.00'],
            [$companies[1], 'DEMO-2026-002', 'preparing_application', $users['second_project_manager'], '2400000.00'],
            [$companies[2], 'DEMO-2026-003', 'awaiting_assignment', null, '950000.00'],
        ];

        foreach ($dealDefinitions as [$company, $reference, $statusCode, $projectManager, $amount]) {
            $deal = Deal::query()->updateOrCreate(
                ['reference_no' => $reference],
                [
                    'company_id' => $company->id,
                    'program_version_id' => $version->id,
                    'status_id' => $dealStatuses[$statusCode]->id,
                    'status_changed_at' => now(),
                    'pm_user_id' => $projectManager?->id,
                    'opened_by_user_id' => $users['marketing']->id,
                    'requested_amount' => $amount,
                    'priority' => 'normal',
                ],
            );

            StatusHistory::query()->firstOrCreate(
                ['deal_id' => $deal->id, 'exited_at' => null],
                [
                    'status_id' => $dealStatuses[$statusCode]->id,
                    'status_label_snapshot' => $dealStatuses[$statusCode]->label,
                    'workflow_revision_id' => $revision->id,
                    'entered_at' => $deal->status_changed_at,
                    'changed_by' => $users['marketing']->id,
                    'reason' => 'demo veri kurulumu',
                ],
            );

            $this->seedDealDocuments($deal, $statusCode, $projectManager);

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

    private function seedDealDocuments(Deal $deal, string $dealStatus, ?User $projectManager): void
    {
        $templates = DocTemplate::query()
            ->where('program_version_id', $deal->program_version_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $index => $template) {
            $document = DealDocument::query()->firstOrCreate(
                ['deal_id' => $deal->id, 'source_doc_template_id' => $template->id],
                [
                    'source_program_version_id' => $deal->program_version_id,
                    'name_snapshot' => $template->name,
                    'description_snapshot' => $template->description,
                    'required_snapshot' => $template->is_required,
                    'condition_snapshot' => $template->condition,
                    'status' => 'requested',
                    'requested_at' => now()->subDays(3),
                ],
            );

            if ($projectManager === null) {
                continue;
            }

            if ($dealStatus === 'preparing_application') {
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
