<?php

declare(strict_types=1);

namespace App\Support\Staging;

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Rules\StrongPassword;
use App\Domain\Crm\Actions\ConvertLead;
use App\Domain\Crm\Actions\CreateCompanyOpportunity;
use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class ProvisionStagingEnvironment
{
    public function __construct(
        private readonly SaveTeam $saveTeam,
        private readonly SaveCompanyDirectoryEntry $saveCompany,
        private readonly SaveContact $saveContact,
        private readonly CreateCompanyOpportunity $createOpportunity,
        private readonly RecordInteraction $recordInteraction,
        private readonly StatusMachineContract $statusMachine,
        private readonly ConvertLead $convertLead,
        private readonly AssignDeal $assignDeal,
    ) {}

    /**
     * @param  array<string, array{name: string, email: string, password: string, role: string, data_scope: string}>  $accounts
     * @return array<string, User>
     */
    public function execute(array $accounts): array
    {
        // 1. Ortam Koruması: Production veya staging dışındaki ortamlarda çalıştırılamaz
        if (app()->environment('production') || config('app.env') === 'production') {
            throw new RuntimeException('Staging provizyonu kesinlikle production ortamında çalıştırılamaz.');
        }

        if (! app()->environment('staging') && config('app.env') !== 'staging') {
            throw new RuntimeException('Staging provizyonu yalnızca APP_ENV=staging ortamında çalıştırılabilir.');
        }

        // 2. Parola ve E-posta Güvenliği Doğrulaması (Ortak StrongPassword kuralı)
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Geçersiz e-posta adresi: {$key}");
            }

            if (! StrongPassword::isValid($account['password'])) {
                throw ValidationException::withMessages([
                    $key => __('management.validation.password_strong'),
                ]);
            }
        }

        return DB::transaction(function () use ($accounts): array {
            // Referans verilerini garantiye al
            (new ReferenceDataSeeder)->setContainer(app())->run();

            $createdUsers = [];

            // 3. Kullanıcıları oluştur ve rolleri/kapsamları ata
            foreach ($accounts as $key => $config) {
                $user = User::query()->firstOrNew([
                    'email' => $config['email'],
                ]);

                $user->name = $config['name'];
                $user->password = Hash::make($config['password']);
                $user->is_active = true;
                $user->data_scope = $config['data_scope'];
                $user->save();

                $user->syncRoles([$config['role']]);

                if ($config['role'] === 'Sistem Yöneticisi') {
                    $user->givePermissionTo('page.access_management');
                }

                $createdUsers[$key] = $user;
            }

            $marketing = $createdUsers['marketing'] ?? null;
            $pm = $createdUsers['pm'] ?? null;
            $authority = $createdUsers['authority'] ?? null;
            $admin = $createdUsers['admin'] ?? null;

            // 4. Pilot Takımını oluştur (PM -> manager, Marketing -> member)
            if ($pm !== null && $marketing !== null && $admin !== null) {
                $existingTeam = Team::query()->where('name', 'Kurgusal Pilot Takımı')->first();

                $this->saveTeam->execute(
                    team: $existingTeam,
                    data: [
                        'name' => 'Kurgusal Pilot Takımı',
                        'manager_id' => $pm->id,
                        'member_ids' => [$marketing->id],
                        'is_active' => true,
                        'change_reason' => 'Staging pilot ortamı otomatik provizyonu',
                    ],
                    actor: $admin,
                );
            }

            // 5. Kurgusal Firma, İletişim Kişisi ve İş Akışı (Yalnızca Domain Action'ları ile)
            if ($marketing !== null && $pm !== null && $authority !== null) {
                $taxNumber = '1234567890';
                $existingCompany = Company::query()->where('tax_number', $taxNumber)->first();

                if ($existingCompany === null) {
                    $company = $this->saveCompany->execute(
                        company: null,
                        data: [
                            'legal_name' => 'Kurgusal Pilot İnovasyon A.Ş.',
                            'industry' => 'manufacturing',
                            'city' => 'Ankara',
                            'district' => 'Çankaya',
                            'tax_number' => $taxNumber,
                            'is_active' => true,
                        ],
                        actor: $marketing,
                    );

                    $contact = $this->saveContact->create(
                        companyId: $company->id,
                        actorId: $marketing->id,
                        fullName: 'Ahmet Kurgusal',
                        phone: '+905550000001',
                        email: 'pilot-yetkili@example.invalid',
                        title: 'Genel Müdür',
                        emailConsent: true,
                        isPrimary: true,
                        recordSource: 'manual',
                    );

                    // 6. Kurgusal İş Akışı: Fırsat -> Görüşme -> Teklif -> İş Alındı -> PM Atama
                    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->where('is_active', true)->sole();
                    $version = $program->latestVersion()->where('is_active', true)->sole();

                    // Fırsat oluştur (CreateCompanyOpportunity -> status: new)
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
                        note: 'İlk telefon görüşmesi yapıldı. Firma Yeşil Sanayi Destek Programı ile ilgileniyor.',
                        contactId: $contact->id,
                    );

                    // Statü geçişleri (new -> called -> interested -> proposal_sent)
                    $calledStatus = Status::query()->where('type', 'lead')->where('code', 'called')->where('is_active', true)->sole();
                    $interestedStatus = Status::query()->where('type', 'lead')->where('code', 'interested')->where('is_active', true)->sole();
                    $proposalSentStatus = Status::query()->where('type', 'lead')->where('code', 'proposal_sent')->where('is_active', true)->sole();
                    $wonStatus = Status::query()->where('type', 'lead')->where('code', 'won')->where('is_active', true)->sole();

                    $this->statusMachine->transition(new StatusTransition(SubjectType::Lead, $lead->id, $calledStatus->id, $marketing->id));
                    $this->statusMachine->transition(new StatusTransition(SubjectType::Lead, $lead->id, $interestedStatus->id, $marketing->id));
                    $this->statusMachine->transition(new StatusTransition(SubjectType::Lead, $lead->id, $proposalSentStatus->id, $marketing->id));

                    // Fırsatı İşe Dönüştür (ConvertLead -> proposal_sent -> won -> deal created + checklist generated)
                    $dealId = $this->convertLead->handle(
                        leadId: $lead->id,
                        wonStatusId: $wonStatus->id,
                        programVersionId: $version->id,
                        actorId: $marketing->id,
                    );

                    // Dosyayı PM'e Ata (AssignDeal -> awaiting_assignment -> pm_assigned)
                    $pmAssignedStatus = Status::query()
                        ->where('type', 'deal')
                        ->where('code', 'pm_assigned')
                        ->where('is_active', true)
                        ->sole();

                    $deal = $this->assignDeal->handle(
                        dealId: $dealId,
                        projectManagerId: $pm->id,
                        targetStatusId: $pmAssignedStatus->id,
                        actorId: $authority->id,
                    );

                    $deal->update([
                        'requested_amount' => '6000000.00',
                    ]);
                }
            }

            return $createdUsers;
        });
    }
}
