<?php

declare(strict_types=1);
use App\Filament\Pages\DealBoard;
use App\Filament\Pages\DealDetail;
use App\Filament\Pages\LeadBoard;
use App\Filament\Pages\LeadDetail;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\PendingAssignments;
use App\Filament\Pages\Reports;
use App\Filament\Pages\TodayCalls;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\DocTemplates\DocTemplateResource;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\ProgramVersions\ProgramVersionResource;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Filament\Resources\Statuses\StatusResource;
use App\Filament\Resources\Transitions\TransitionResource;
use App\Filament\Resources\Users\UserResource;

return [
    'page_management_permission' => 'page.access_managed',
    'pages' => [
        'page.dashboard' => ['label' => 'management.page_access.dashboard', 'fallback' => 'program.view', 'classes' => [OperationsDashboard::class]],
        'page.companies' => ['label' => 'management.page_access.companies', 'fallback' => 'company.manage', 'classes' => [CompanyResource::class]],
        'page.customers' => ['label' => 'management.page_access.customers', 'fallback' => 'deal.view', 'classes' => [CustomerResource::class]],
        'page.today_calls' => ['label' => 'management.page_access.today_calls', 'fallback' => 'lead.manage', 'classes' => [TodayCalls::class]],
        'page.opportunities' => ['label' => 'management.page_access.opportunities', 'fallback' => 'lead.manage', 'classes' => [LeadBoard::class, LeadDetail::class]],
        'page.pending_assignments' => ['label' => 'management.page_access.pending_assignments', 'fallback' => 'deal.assign', 'classes' => [PendingAssignments::class]],
        'page.deals' => ['label' => 'management.page_access.deals', 'fallback' => 'deal.view', 'classes' => [DealBoard::class, DealDetail::class]],
        'page.reports' => ['label' => 'management.page_access.reports', 'fallback' => 'report.view', 'classes' => [Reports::class]],
        'page.service_workflows' => ['label' => 'management.page_access.service_workflows', 'fallback' => 'program.manage', 'classes' => [ServiceWorkflowResource::class]],
        'page.programs' => ['label' => 'management.page_access.programs', 'fallback' => 'program.manage', 'classes' => [ProgramResource::class, ProgramVersionResource::class]],
        'page.document_templates' => ['label' => 'management.page_access.document_templates', 'fallback' => 'program.manage', 'classes' => [DocTemplateResource::class]],
        'page.workflow_settings' => ['label' => 'management.page_access.workflow_settings', 'fallback' => 'system.settings', 'classes' => [StatusResource::class, TransitionResource::class]],
        'page.access_management' => ['label' => 'management.page_access.access_management', 'fallback' => 'system.users', 'classes' => [UserResource::class]],
    ],
];
