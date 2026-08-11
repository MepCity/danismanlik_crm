<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\AccessServiceProvider;
use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Policies\BreakGlassGrantPolicy;
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
use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Access\Policies\StatusPolicy;
use App\Domain\Access\Policies\TaskPolicy;
use App\Domain\Access\Policies\TransitionPolicy;
use App\Domain\Access\Policies\UserPolicy;
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
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Document\DocumentServiceProvider;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Program\ProgramServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

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
        Gate::policy(Status::class, StatusPolicy::class);
        Gate::policy(Transition::class, TransitionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(BreakGlassGrant::class, BreakGlassGrantPolicy::class);
    }
}
