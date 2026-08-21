<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Domain\Crm\Actions\StartCustomerFlow;
use App\Domain\Program\Services\ActiveProgramVersionReader;
use App\Filament\Pages\DealDetail;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_customer_flow')
                ->label(__('panel.customers.start'))
                ->icon('heroicon-o-play')
                ->visible(fn (): bool => auth()->user()?->can('deal.create') === true)
                ->modalHeading(__('panel.customers.start'))
                ->modalDescription(__('panel.customers.start_help'))
                ->modalSubmitActionLabel(__('panel.customers.submit'))
                ->form([
                    Select::make('company_id')
                        ->label(__('panel.customers.company'))
                        ->options(fn (): array => CompanyResource::getEloquentQuery()
                            ->where('is_active', true)
                            ->orderBy('legal_name')
                            ->pluck('legal_name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Select::make('program_version_id')
                        ->label(__('panel.customers.program'))
                        ->options(function (): array {
                            $options = [];

                            foreach (app(ActiveProgramVersionReader::class)->all() as $version) {
                                $options[$version->id] = $version->label();
                            }

                            return $options;
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    $dealId = app(StartCustomerFlow::class)->execute(
                        (int) $data['company_id'],
                        (int) $data['program_version_id'],
                        $actor,
                    );
                    Notification::make()->title(__('panel.customers.started'))->success()->send();
                    $this->redirect(DealDetail::getUrl(['deal' => $dealId]), navigate: true);
                }),
        ];
    }

    public function getSubheading(): string
    {
        return __('panel.customers.description');
    }
}
