<?php

declare(strict_types=1);

namespace App\Support\Staging;

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Actions\UpsertPilotUser;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Rules\StrongPassword;
use App\Domain\Crm\Actions\ConvertLead;
use App\Domain\Crm\Actions\CreateCompanyOpportunity;
use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Actions\UpdateDealAmount;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Deal\Services\TransitionPathResolverContract;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;

final readonly class ProvisionStagingEnvironment
{
    public function __construct(
        private UpsertPilotUser $upsertUser,
        private SaveTeam $saveTeam,
        private SaveCompanyDirectoryEntry $saveCompany,
        private SaveContact $saveContact,
        private CreateCompanyOpportunity $createOpportunity,
        private RecordInteraction $recordInteraction,
        private StatusMachineContract $statusMachine,
        private TransitionPathResolverContract $pathResolver,
        private ConvertLead $convertLead,
        private AssignDeal $assignDeal,
        private UpdateDealAmount $updateDealAmount,
    ) {}

    /**
     * @param  array<string, array{name: string, email: string, password: string, role: string, data_scope: string}>  $accounts
     * @return array<string, User>
     */
    public function execute(array $accounts): array
    {
        // 1. Ortam Koruması: Production veya staging dışındaki ortamlarda çalıştırılamaz
        if (app()->environment('production') || config('app.env') === 'production') {
            throw new RuntimeException(__('management.staging_provision.error_production'));
        }

        if (! app()->environment('staging') && config('app.env') !== 'staging') {
            throw new RuntimeException(__('management.staging_provision.error_environment'));
        }

        // 2. Parola ve E-posta Güvenliği Doğrulaması (Ortak StrongPassword kuralı)
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException(__('management.staging_provision.invalid_email', ['field' => 'STAGING_'.strtoupper($key).'_EMAIL']));
            }

            if (! StrongPassword::isValid($account['password'])) {
                throw ValidationException::withMessages([
                    $key => __('management.validation.password_strong'),
                ]);
            }
        }

        return DB::transaction(function () use ($accounts): array {
            // Referans verilerini ihtiyaç varsa garantiye al
            if (Status::query()->where('is_active', true)->doesntExist() || Role::query()->doesntExist()) {
                (new ReferenceDataSeeder)->setContainer(app())->run();
            }

            // 3. Kullanıcıları Domain Action ile oluştur veya varsa güncelle (Idempotent)
            $createdUsers = [];
            foreach ($accounts as $key => $config) {
                $directPerms = $config['role'] === 'Sistem Yöneticisi' ? ['page.access_management'] : [];
                $user = $this->upsertUser->execute(
                    email: $config['email'],
                    password: $config['password'],
                    name: $config['name'],
                    roleName: $config['role'],
                    dataScope: $config['data_scope'],
                    directPermissions: $directPerms,
                );

                $createdUsers[$key] = $user;
            }

            $marketing = $createdUsers['marketing'] ?? null;
            $pm = $createdUsers['pm'] ?? null;
            $authority = $createdUsers['authority'] ?? null;
            $admin = $createdUsers['admin'] ?? null;

            // 4. Pilot Takımını oluştur / güncelle (Idempotent: değişiklik yoksa SaveTeam çağrılmaz)
            if ($pm !== null && $marketing !== null && $admin !== null) {
                $teamName = (string) __('management.staging_provision.pilot_data.team_name');
                $existingTeam = Team::query()->where('name', $teamName)->first();

                $needsTeamSave = true;
                if ($existingTeam !== null) {
                    $existingMemberIds = $existingTeam->members()->pluck('users.id')->sort()->values()->all();
                    $targetMemberIds = collect([$pm->id, $marketing->id])->sort()->values()->all();

                    if ($existingTeam->manager_id === $pm->id && $existingMemberIds === $targetMemberIds && $existingTeam->is_active) {
                        $needsTeamSave = false;
                    }
                }

                if ($needsTeamSave) {
                    $this->saveTeam->execute(
                        team: $existingTeam,
                        data: [
                            'name' => $teamName,
                            'manager_id' => $pm->id,
                            'member_ids' => [$marketing->id],
                            'is_active' => true,
                            'change_reason' => __('management.staging_provision.pilot_data.team_reason'),
                        ],
                        actor: $admin,
                    );
                }
            }

            // 5. Kurgusal Firma, İletişim Kişisi ve İş Akışı (Yalnızca Domain Action'ları ile)
            if ($marketing !== null && $pm !== null && $authority !== null) {
                $taxNumber = '1234567890';
                $existingCompany = Company::query()->where('tax_number', $taxNumber)->first();

                if ($existingCompany === null) {
                    $company = $this->saveCompany->execute(
                        company: null,
                        data: [
                            'legal_name' => __('management.staging_provision.pilot_data.company_legal_name'),
                            'industry' => 'manufacturing',
                            'city' => __('management.staging_provision.pilot_data.company_city'),
                            'district' => __('management.staging_provision.pilot_data.company_district'),
                            'tax_number' => $taxNumber,
                            'is_active' => true,
                        ],
                        actor: $marketing,
                    );

                    $contact = $this->saveContact->create(
                        companyId: $company->id,
                        actorId: $marketing->id,
                        fullName: (string) __('management.staging_provision.pilot_data.contact_name'),
                        phone: (string) __('management.staging_provision.pilot_data.contact_phone'),
                        email: (string) __('management.staging_provision.pilot_data.contact_email'),
                        title: (string) __('management.staging_provision.pilot_data.contact_title'),
                        emailConsent: true,
                        isPrimary: true,
                        recordSource: 'manual',
                    );

                    // 6. Kurgusal İş Akışı: Fırsat -> Görüşme -> Statü Geçişleri -> İş Alındı -> PM Atama
                    $program = Program::query()->where('is_active', true)->firstOrFail();
                    $version = $program->latestVersion()->where('is_active', true)->firstOrFail();

                    // Fırsat oluştur (CreateCompanyOpportunity -> initial lead status)
                    $lead = $this->createOpportunity->execute(
                        companyId: $company->id,
                        programVersionId: $version->id,
                        actor: $marketing,
                        contactId: $contact->id,
                        nextCallAt: null,
                    );

                    // Görüşme kaydı (RecordInteraction)
                    $this->recordInteraction->forLead(
                        leadId: $lead->id,
                        actorId: $marketing->id,
                        type: 'call',
                        occurredAt: now(),
                        outcome: 'interested',
                        note: (string) __('management.staging_provision.pilot_data.call_note'),
                        contactId: $contact->id,
                    );

                    // converts_to_deal statüsü DB anlamsal alanları üzerinden bulunur
                    $wonLeadStatus = Status::query()
                        ->where('type', 'lead')
                        ->where('converts_to_deal', true)
                        ->where('is_active', true)
                        ->sole();

                    $path = $this->pathResolver->findShortestPath(
                        subjectType: SubjectType::Lead,
                        subjectId: $lead->id,
                        targetStatusId: $wonLeadStatus->id,
                        actorId: $marketing->id,
                    );

                    // converts_to_deal öncesindeki ara statü geçişleri yürütülür
                    $pathCount = count($path);
                    for ($i = 1; $i < $pathCount - 1; $i++) {
                        $this->statusMachine->transition(new StatusTransition(
                            subjectType: SubjectType::Lead,
                            subjectId: $lead->id,
                            targetStatusId: $path[$i],
                            actorId: $marketing->id,
                        ));
                    }

                    // Fırsatı İşe Dönüştür (ConvertLead -> converts_to_deal status -> deal created + checklist generated)
                    $dealId = $this->convertLead->handle(
                        leadId: $lead->id,
                        wonStatusId: $wonLeadStatus->id,
                        programVersionId: $version->id,
                        actorId: $marketing->id,
                    );

                    // Dosyayı PM'e Ata (AssignDeal -> deterministik geçiş çözümü üzerinden)
                    $pmAssignTransition = $this->pathResolver->findDeterministicTransition(
                        subjectType: SubjectType::Deal,
                        subjectId: $dealId,
                        actorId: $authority->id,
                        requiredPermission: 'deal.assign',
                    );

                    $this->assignDeal->handle(
                        dealId: $dealId,
                        projectManagerId: $pm->id,
                        targetStatusId: $pmAssignTransition->to_status_id,
                        actorId: $authority->id,
                    );

                    // Talep tutarını Domain Action üzerinden güncelle
                    $this->updateDealAmount->execute(
                        dealId: $dealId,
                        requestedAmount: '6000000.00',
                        actor: $authority,
                    );
                }
            }

            return $createdUsers;
        });
    }
}
