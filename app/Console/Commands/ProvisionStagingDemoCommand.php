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

    protected $description = 'Geçici staging / pilot ortamı için 4 izole rolü ve kurgusal CRM iş akışını hazırlar.';

    public function handle(ProvisionStagingEnvironment $provisioner): int
    {
        // Ortam Kontrolü: Yalnızca APP_ENV=staging ortamında çalıştırılabilir
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error('Staging provizyonu kesinlikle production ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        if (! app()->environment('staging') && config('app.env') !== 'staging') {
            $this->error('Staging provizyon komutu yalnızca APP_ENV=staging ortamında çalıştırılabilir.');

            return self::FAILURE;
        }

        $accounts = [
            'marketing' => [
                'name' => 'Pilot Pazarlama',
                'email' => $this->getSecret('STAGING_MARKETING_EMAIL', 'pazarlama@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_MARKETING_PASSWORD'),
                'role' => 'Pazarlama',
                'data_scope' => 'own',
            ],
            'pm' => [
                'name' => 'Pilot Proje Yöneticisi',
                'email' => $this->getSecret('STAGING_PM_EMAIL', 'pm@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_PM_PASSWORD'),
                'role' => 'Proje Yöneticisi',
                'data_scope' => 'team',
            ],
            'authority' => [
                'name' => 'Pilot Şirket Yetkilisi',
                'email' => $this->getSecret('STAGING_COMPANY_AUTHORITY_EMAIL', 'yetkili@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_COMPANY_AUTHORITY_PASSWORD'),
                'role' => 'Şirket Yetkilisi',
                'data_scope' => 'all',
            ],
            'admin' => [
                'name' => 'Pilot Sistem Yöneticisi',
                'email' => $this->getSecret('STAGING_SYSTEM_ADMIN_EMAIL', 'admin@pilot.bizlife.invalid'),
                'password' => $this->getSecret('STAGING_SYSTEM_ADMIN_PASSWORD'),
                'role' => 'Sistem Yöneticisi',
                'data_scope' => 'none',
            ],
        ];

        // Sır ve parola doğrulamaları
        foreach ($accounts as $key => $account) {
            if (! filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                $this->error('Geçersiz e-posta adresi: STAGING_'.strtoupper($key).'_EMAIL');

                return self::FAILURE;
            }

            if (! StrongPassword::isValid($account['password'])) {
                $this->error('Güvensiz veya eksik parola (en az 12 karakter, büyük/küçük harf, rakam ve sembol içermeli): STAGING_'.strtoupper($key).'_PASSWORD');

                return self::FAILURE;
            }
        }

        try {
            $provisioner->execute($accounts);

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

    private function getSecret(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        return (string) $value;
    }
}
