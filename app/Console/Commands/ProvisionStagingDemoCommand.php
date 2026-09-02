<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Rules\StrongPassword;
use App\Support\Staging\ProvisionStagingEnvironment;
use Illuminate\Console\Command;
use Throwable;

final class ProvisionStagingDemoCommand extends Command
{
    protected $signature = 'system:provision-staging-demo';

    public function __construct()
    {
        $this->description = (string) __('management.staging_provision.description');
        parent::__construct();
    }

    public function handle(ProvisionStagingEnvironment $provisioner): int
    {
        // Ortam Kontrolü: Yalnızca APP_ENV=staging ortamında çalıştırılabilir
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error(__('management.staging_provision.error_production'));

            return self::FAILURE;
        }

        if (! app()->environment('staging') && config('app.env') !== 'staging') {
            $this->error(__('management.staging_provision.error_environment'));

            return self::FAILURE;
        }

        $accounts = [
            'marketing' => [
                'name' => (string) __('management.staging_provision.pilot_data.marketing_name'),
                'email' => $this->getSecret('STAGING_MARKETING_EMAIL', 'pazarlama@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_MARKETING_PASSWORD'),
                'role' => 'Pazarlama',
                'data_scope' => 'own',
            ],
            'pm' => [
                'name' => (string) __('management.staging_provision.pilot_data.pm_name'),
                'email' => $this->getSecret('STAGING_PM_EMAIL', 'pm@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_PM_PASSWORD'),
                'role' => 'Proje Yöneticisi',
                'data_scope' => 'team',
            ],
            'authority' => [
                'name' => (string) __('management.staging_provision.pilot_data.authority_name'),
                'email' => $this->getSecret('STAGING_COMPANY_AUTHORITY_EMAIL', 'yetkili@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_COMPANY_AUTHORITY_PASSWORD'),
                'role' => 'Şirket Yetkilisi',
                'data_scope' => 'all',
            ],
            'admin' => [
                'name' => (string) __('management.staging_provision.pilot_data.admin_name'),
                'email' => $this->getSecret('STAGING_SYSTEM_ADMIN_EMAIL', 'admin@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_SYSTEM_ADMIN_PASSWORD'),
                'role' => 'Sistem Yöneticisi',
                'data_scope' => 'none',
            ],
        ];

        // Sır ve parola doğrulamaları
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                $this->error(__('management.staging_provision.invalid_email', ['field' => 'STAGING_'.strtoupper($key).'_EMAIL']));

                return self::FAILURE;
            }

            if (! StrongPassword::isValid($account['password'])) {
                $this->error(__('management.staging_provision.weak_password', ['field' => 'STAGING_'.strtoupper($key).'_PASSWORD']));

                return self::FAILURE;
            }
        }

        try {
            $provisioner->execute($accounts);

            $this->info(__('management.staging_provision.success'));
            $this->table(
                [
                    __('management.staging_provision.table.role'),
                    __('management.staging_provision.table.name'),
                    __('management.staging_provision.table.email'),
                    __('management.staging_provision.table.scope'),
                ],
                array_map(fn ($acc) => [$acc['role'], $acc['name'], $acc['email'], $acc['data_scope']], $accounts),
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(__('management.staging_provision.failure', ['message' => $e->getMessage()]));

            return self::FAILURE;
        }
    }

    private function getSecret(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        return (string) $value;
    }
}
