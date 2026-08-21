<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceWorkflows\Pages;

use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListServiceWorkflows extends ListRecords
{
    protected static string $resource = ServiceWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('management.workflow_setup.actions.new'))];
    }

    public function getSubheading(): string
    {
        return __('management.workflow_setup.description');
    }
}
