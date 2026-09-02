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
        {--password= : En az 12 karakterlik güçlü parola}
        {--force : Mevcut etkin Sistem Yöneticisi olsa bile oluştur}';

    protected $description = 'Sistem için güvenli ilk yönetici hesabını oluşturur.';

    public function handle(BootstrapAdmin $bootstrapAdmin): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Yönetici Adı'));
        $email = (string) ($this->option('email') ?: $this->ask('Yönetici E-posta'));
        $password = (string) ($this->option('password') ?: $this->secret('Yönetici Parolası'));
        $force = (bool) $this->option('force');

        try {
            $user = $bootstrapAdmin->execute([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ], force: $force);

            $this->info("Sistem yöneticisi başarıyla oluşturuldu: {$user->email}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
