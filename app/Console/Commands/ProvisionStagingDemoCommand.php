<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Services\PageAccess;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\Program;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class ProvisionStagingDemoCommand extends Command
{
    protected $signature = 'system:provision-staging-demo';

    protected $description = 'Staging ortamı için güvenli pilot kullanıcılarını ve kurgusal demo verisini kurar.';

    public function handle(): int
    {
        $accounts = [
            'marketing' => [
                'name' => 'Demo Pazarlama',
                'email' => $this->getSecret('STAGING_MARKETING_EMAIL'),
                'password' => $this->getSecret('STAGING_MARKETING_PASSWORD'),
                'role' => 'Pazarlama',
                'data_scope' => 'own',
            ],
            'pm' => [
                'name' => 'Demo Proje Yöneticisi',
                'email' => $this->getSecret('STAGING_PM_EMAIL'),
                'password' => $this->getSecret('STAGING_PM_PASSWORD'),
                'role' => 'Proje Yöneticisi',
                'data_scope' => 'team',
            ],
            'authority' => [
                'name' => 'Demo Şirket Yetkilisi',
                'email' => $this->getSecret('STAGING_COMPANY_AUTHORITY_EMAIL'),
                'password' => $this->getSecret('STAGING_COMPANY_AUTHORITY_PASSWORD'),
                'role' => 'Şirket Yetkilisi',
                'data_scope' => 'all',
            ],
            'admin' => [
                'name' => 'Demo Sistem Yöneticisi',
                'email' => $this->getSecret('STAGING_SYSTEM_ADMIN_EMAIL'),
                'password' => $this->getSecret('STAGING_SYSTEM_ADMIN_PASSWORD'),
                'role' => 'Sistem Yöneticisi',
                'data_scope' => 'none',
            ],
        ];

        // Validation
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                $this->error('Geçersiz e-posta adresi: STAGING_'.strtoupper($key).'_EMAIL');

                return self::FAILURE;
            }

            if (mb_strlen($account['password']) < 12) {
                $this->error('Güvensiz veya eksik parola (en az 12 karakter olmalı): STAGING_'.strtoupper($key).'_PASSWORD');

                return self::FAILURE;
            }
        }

        try {
            if (Role::query()->count() === 0) {
                (new ReferenceDataSeeder)->setContainer(app())->run();
            }

            DB::transaction(function () use ($accounts): void {
                $createdUsers = [];

                foreach ($accounts as $key => $account) {
                    $user = User::query()->where('email', $account['email'])->first() ?? new User;
                    $user->name = $account['name'];
                    $user->email = $account['email'];
                    $user->password = Hash::make($account['password']);
                    $user->data_scope = $account['data_scope'];
                    $user->is_active = true;
                    $user->deactivated_at = null;
                    $user->save();

                    $role = Role::query()->where('name', $account['role'])->firstOrFail();
                    $user->roles()->sync([(int) $role->id]);

                    if ($key === 'admin') {
                        $pageAccess = app(PageAccess::class);
                        $pagePermissions = Permission::query()
                            ->whereIn('name', $pageAccess->permissionNames())
                            ->where('guard_name', 'web')
                            ->get();
                        $marker = Permission::query()
                            ->where('name', (string) config('access.page_management_permission'))
                            ->where('guard_name', 'web')
                            ->first();

                        $permissionsToSync = $pagePermissions;
                        if ($marker !== null) {
                            $permissionsToSync = $permissionsToSync->push($marker);
                        }
                        $user->syncPermissions($permissionsToSync);
                    }

                    RolePermissionHistory::query()->create([
                        'subject_type' => 'user',
                        'subject_id' => $user->id,
                        'change_type' => 'staging_demo_provisioned',
                        'old_value' => null,
                        'new_value' => [
                            'name' => $user->name,
                            'role' => $account['role'],
                            'data_scope' => $account['data_scope'],
                        ],
                        'changed_by' => $user->id,
                        'reason' => 'Staging pilot ortamı kurulumu',
                    ]);

                    $createdUsers[$key] = $user;
                }

                app(PermissionRegistrar::class)->forgetCachedPermissions();

                // Setup Team
                $pm = $createdUsers['pm'];
                $marketing = $createdUsers['marketing'];

                $team = Team::query()->firstOrNew(['name' => 'Kurgusal Pilot Takımı']);
                $team->manager_id = $pm->id;
                $team->is_active = true;
                $team->save();
                $team->members()->sync([
                    $pm->id => ['role' => 'member'],
                    $marketing->id => ['role' => 'member'],
                ]);

                // Fictional CRM Data
                $company = Company::query()->firstOrNew(['legal_name' => 'Kurgusal Anadolu Makine Sanayi A.Ş.']);
                $company->tax_number = '0000000001';
                $company->industry = 'manufacturing';
                $company->city = 'Ankara';
                $company->owner_user_id = $marketing->id;
                $company->is_active = true;
                $company->save();

                $contact = Contact::query()->firstOrNew([
                    'company_id' => $company->id,
                    'email' => 'kurgusal.yetkili@ornekfirma.invalid',
                ]);
                $contact->full_name = 'Kurgusal Yetkili';
                $contact->phone = '5550000001';
                $contact->data_source = 'manual';
                $contact->is_primary = true;
                $contact->is_active = true;
                $contact->save();

                $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->first();
                if ($program !== null) {
                    $version = $program->versions()->first();
                    $dealStatus = Status::query()->where('type', 'deal')->first();
                    $leadStatus = Status::query()->where('type', 'lead')->first();

                    if ($version !== null && $leadStatus !== null) {
                        $lead = Lead::query()->firstOrNew([
                            'company_id' => $company->id,
                            'interested_program_version_id' => $version->id,
                        ]);
                        $lead->status_id = $leadStatus->id;
                        $lead->owner_user_id = $marketing->id;
                        $lead->save();
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
                        $deal->save();
                    }
                }
            });

            $this->info('Staging demo hesapları ve kurgusal pilot verisi başarıyla oluşturuldu.');
            $this->table(
                ['Rol', 'Ad', 'E-posta', 'Kapsam'],
                array_map(fn ($acc) => [$acc['role'], $acc['name'], $acc['email'], $acc['data_scope']], $accounts),
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Kurulum hatası: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function getSecret(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
        }

        return (string) $value;
    }
}
