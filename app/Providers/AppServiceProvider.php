<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\AccessServiceProvider;
use App\Domain\Collaboration\CollaborationServiceProvider;
use App\Domain\Crm\CrmServiceProvider;
use App\Domain\Deal\DealServiceProvider;
use App\Domain\Document\DocumentServiceProvider;
use App\Domain\Program\ProgramServiceProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(SupportServiceProvider::class);
        $this->app->register(DomainEventServiceProvider::class);
        $this->app->register(AccessServiceProvider::class);
        $this->app->register(CollaborationServiceProvider::class);
        $this->app->register(CrmServiceProvider::class);
        $this->app->register(DealServiceProvider::class);
        $this->app->register(DocumentServiceProvider::class);
        $this->app->register(ProgramServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
