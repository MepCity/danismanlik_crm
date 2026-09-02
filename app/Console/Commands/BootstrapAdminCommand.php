<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Access\Actions\BootstrapAdmin;
use Illuminate\Console\Command;
use Throwable;

final class BootstrapAdminCommand extends Command
{
    protected $signature = 'system:bootstrap-admin
        {--name= : Yönetici adı}
        {--email= : Yönetici e-posta adresi}
        {--force : Mevcut etkin Sistem Yöneticisi olsa bile oluştur}';

    protected $description = 'Sistem için güvenli ilk yönetici hesabını oluşturur.';

    public function handle(BootstrapAdmin $bootstrapAdmin): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Yönetici Adı'));
        $email = (string) ($this->option('email') ?: $this->ask('Yönetici E-posta'));

        $envPassword = (string) (getenv('ADMIN_BOOTSTRAP_PASSWORD') ?: ($_ENV['ADMIN_BOOTSTRAP_PASSWORD'] ?? $_SERVER['ADMIN_BOOTSTRAP_PASSWORD'] ?? ''));
        $password = $envPassword !== '' ? $envPassword : (string) $this->secret('Yönetici Parolası (en az 12 karakter, büyük/küçük harf, rakam, sembol)');
        $force = (bool) $this->option('force');

        try {
            $user = $bootstrapAdmin->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ], force: $force);

            $this->info(__('management.validation.admin_created', ['email' => $user->email]));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
