<?php

declare(strict_types=1);

namespace App\Support\Staging;

use App\Domain\Access\Actions\SaveTeam;
use App\Domain\Access\Models\Team;
use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Services\ChecklistGenerator;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

final class ProvisionStagingEnvironment
{
    public function __construct(
        private readonly ChecklistGenerator $checklistGenerator,
        private readonly ActivityRecorder $activities,
        private readonly SaveTeam $saveTeam,
    ) {}

    /**
     * @param  array<string, array{name: string, email: string, password: string, role: string, data_scope: string}>  $accounts
     * @return array<string, User>
     */
    public function execute(array $accounts, bool $allowTesting = false): array
    {
        // 1. Ortam Koruması: Production ve Local ortamlarında kesinlikle çalıştırılamaz
        if (app()->environment('production') || config('app.env') === 'production') {
            throw new RuntimeException('Staging provizyonu kesinlikle production ortamında çalıştırılamaz.');
        }

        if (! app()->environment('staging') && config('app.env') !== 'staging' && ! $allowTesting) {
            throw new RuntimeException('Staging provizyonu yalnızca APP_ENV=staging ortamında çalıştırılabilir.');
        }

        // 2. Parola Güvenliği Doğrulaması
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Geçersiz e-posta adresi: {$key}");
            }

            if (mb_strlen($account['password']) < 12) {
                throw ValidationException::withMessages([
                    $key => "{$account['name']} için parola en az 12 karakter olmalıdır.",
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

            // 5. Kurgusal Firma ve İletişim Kişisi Oluştur
            if ($marketing !== null && $pm !== null && $authority !== null) {
                $company = Company::query()->firstOrCreate(
                    ['tax_number' => '1234567890'],
                    [
                        'legal_name' => 'Kurgusal Pilot İnovasyon A.Ş.',
                        'short_name' => 'Pilot İnovasyon',
                        'industry' => 'manufacturing',
                        'city' => 'Ankara',
                        'district' => 'Çankaya',
                        'address' => 'Kurgusal Teknokent No: 42',
                        'phone' => '+905550000000',
                        'email' => 'pilot-firma@example.invalid',
                        'owner_user_id' => $marketing->id,
                        'is_active' => true,
                    ]
                );

                $contact = Contact::query()->firstOrCreate(
                    ['company_id' => $company->id, 'email' => 'pilot-yetkili@example.invalid'],
                    [
                        'full_name' => 'Ahmet Kurgusal',
                        'title' => 'Genel Müdür',
                        'phone' => '+905550000001',
                        'data_source' => 'manual',
                        'is_primary' => true,
                        'is_active' => true,
                        'consent_email' => true,
                    ]
                );

                // 6. Kurgusal İş Akışı: Lead -> Deal -> Evrak Listesi
                $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->first();
                $version = $program ? ProgramVersion::query()->where('program_id', $program->id)->first() : null;

                $leadStatus = Status::query()
                    ->where('type', 'lead')
                    ->where('is_initial', true)
                    ->where('is_active', true)
                    ->first();

                $dealStatus = Status::query()
                    ->where('type', 'deal')
                    ->where('is_initial', true)
                    ->where('is_active', true)
                    ->first();

                if ($version !== null && $leadStatus !== null) {
                    $lead = Lead::query()->firstOrNew([
                        'company_id' => $company->id,
                        'interested_program_version_id' => $version->id,
                    ]);
                    $lead->status_id = $leadStatus->id;
                    $lead->owner_user_id = $marketing->id;
                    $lead->save();

                    // İlk görüşme kaydı
                    Interaction::query()->firstOrCreate([
                        'lead_id' => $lead->id,
                        'type' => 'call',
                    ], [
                        'user_id' => $marketing->id,
                        'contact_id' => $contact->id,
                        'direction' => 'outbound',
                        'occurred_at' => now(),
                        'note' => 'İlk telefon görüşmesi yapıldı. Firma Yeşil Sanayi Destek Programı ile ilgileniyor.',
                    ]);
                }

                if ($version !== null && $dealStatus !== null) {
                    $deal = Deal::query()->firstOrNew([
                        'reference_no' => 'PLT-2026-001',
                    ]);
                    $deal->company_id = $company->id;
                    $deal->program_version_id = $version->id;
                    $deal->status_id = $dealStatus->id;
                    $deal->status_changed_at = now();
                    $deal->opened_by_user_id = $marketing->id;
                    $deal->pm_user_id = $pm->id;
                    $deal->requested_amount = '6000000.00';
                    $deal->save();

                    // Evrak kontrol listesi üretimi
                    $this->checklistGenerator->generate($deal->id, $pm->id);

                    // İşlem geçmişi kaydı
                    $this->activities->record(
                        action: 'deal.created',
                        payload: [
                            'company' => ['id' => $company->id, 'name' => $company->legal_name],
                            'program_version_id' => $version->id,
                        ],
                        actorId: $marketing->id,
                        dealId: $deal->id,
                    );
                }
            }

            return $createdUsers;
        });
    }
}
