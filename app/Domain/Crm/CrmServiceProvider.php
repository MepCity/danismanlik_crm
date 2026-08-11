<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Observers\CompanyChecklistObserver;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // CRM servis bağları ilgili iş paketlerinde tanımlanacak.
    }

    public function boot(): void
    {
        Company::observe(CompanyChecklistObserver::class);
    }
}
