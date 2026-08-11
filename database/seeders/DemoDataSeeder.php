<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\DocumentRequirementSuggestion;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
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
            Contact::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => 'yetkili'.($index + 1).'@firma.invalid'],
                [
                    'full_name' => 'Kurgusal Yetkili '.($index + 1),
                    'title' => 'Demo Yetkilisi',
                    'phone' => null,
                    'is_primary' => true,
                    'is_active' => true,
                    'consent_email' => true,
                ],
            );
        }

        $leadStatuses = Status::query()->where('type', 'lead')->get()->keyBy('code');
        foreach ([
            [$companies[0], 'interested'],
            [$companies[1], 'proposal_sent'],
            [$companies[2], 'callback'],
        ] as [$company, $statusCode]) {
            Lead::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'owner_user_id' => $users['marketing']->id,
                    'interested_program_version_id' => $version->id,
                ],
                [
                    'source' => 'demo',
                    'status_id' => $leadStatuses[$statusCode]->id,
                    'next_call_at' => $statusCode === 'callback' ? now()->addWeek() : null,
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

            $this->seedDealDocuments($deal, $statusCode);

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

    private function seedDealDocuments(Deal $deal, string $dealStatus): void
    {
        $templates = DocTemplate::query()
            ->where('program_version_id', $deal->program_version_id)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $index => $template) {
            $documentStatus = match (true) {
                $dealStatus === 'preparing_application' => 'accepted',
                $index < 2 => 'uploaded',
                default => 'requested',
            };

            DealDocument::query()->updateOrCreate(
                ['deal_id' => $deal->id, 'source_doc_template_id' => $template->id],
                [
                    'source_program_version_id' => $deal->program_version_id,
                    'name_snapshot' => $template->name,
                    'description_snapshot' => $template->description,
                    'required_snapshot' => $template->is_required,
                    'condition_snapshot' => $template->condition,
                    'status' => $documentStatus,
                    'requested_at' => now()->subDays(3),
                ],
            );
        }
    }
}
