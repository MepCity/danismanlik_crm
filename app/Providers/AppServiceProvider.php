<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\AccessServiceProvider;
use App\Domain\Access\Policies\CommentPolicy;
use App\Domain\Access\Policies\CompanyPolicy;
use App\Domain\Access\Policies\ContactPolicy;
use App\Domain\Access\Policies\DealDocumentPolicy;
use App\Domain\Access\Policies\DealPolicy;
use App\Domain\Access\Policies\DocTemplatePolicy;
use App\Domain\Access\Policies\FilePolicy;
use App\Domain\Access\Policies\InteractionPolicy;
use App\Domain\Access\Policies\LeadPolicy;
use App\Domain\Access\Policies\ProgramPolicy;
use App\Domain\Access\Policies\ProgramVersionPolicy;
use App\Domain\Access\Policies\TaskPolicy;
use App\Domain\Collaboration\CollaborationServiceProvider;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\CrmServiceProvider;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\DealServiceProvider;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\DocumentServiceProvider;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Program\ProgramServiceProvider;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Interaction::class, InteractionPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(DealDocument::class, DealDocumentPolicy::class);
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Program::class, ProgramPolicy::class);
        Gate::policy(ProgramVersion::class, ProgramVersionPolicy::class);
        Gate::policy(DocTemplate::class, DocTemplatePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
    }
}
