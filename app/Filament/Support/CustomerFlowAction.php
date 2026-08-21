<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Crm\Actions\StartCustomerFlow;
use App\Domain\Crm\Models\Company;
use App\Domain\Program\Services\ActiveProgramVersionReader;
use App\Filament\Pages\DealDetail;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Livewire\Component;

final class CustomerFlowAction
{
    public static function forRecord(): Action
    {
        return self::base()
            ->visible(fn (Company $record): bool => $record->is_active && auth()->user()?->can('deal.create') === true)
            ->form([self::programSelect()])
            ->action(function (Company $record, array $data, Component $livewire): void {
                self::start($record->id, $data, $livewire);
            });
    }

    public static function forSelection(): Action
    {
        return self::base()
            ->visible(fn (): bool => auth()->user()?->can('deal.create') === true)
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
                self::programSelect(),
            ])
            ->action(function (array $data, Component $livewire): void {
                self::start((int) $data['company_id'], $data, $livewire);
            });
    }

    private static function base(): Action
    {
        return Action::make('start_customer_flow')
            ->label(__('panel.customers.start_from_company'))
            ->icon('heroicon-o-play')
            ->modalHeading(__('panel.customers.start'))
            ->modalDescription(__('panel.customers.start_help'))
            ->modalSubmitActionLabel(__('panel.customers.submit'));
    }

    private static function programSelect(): Select
    {
        return Select::make('program_version_id')
            ->label(__('panel.customers.program'))
            ->options(function (): array {
                $options = [];

                foreach (app(ActiveProgramVersionReader::class)->all() as $version) {
                    $options[$version->id] = $version->label();
                }

                return $options;
            })
            ->searchable()
            ->required();
    }

    /** @param array<string, mixed> $data */
    private static function start(int $companyId, array $data, Component $livewire): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $dealId = app(StartCustomerFlow::class)->execute(
            $companyId,
            (int) $data['program_version_id'],
            $actor,
        );
        Notification::make()->title(__('panel.customers.started'))->success()->send();
        $livewire->redirect(DealDetail::getUrl(['deal' => $dealId]), navigate: true);
    }
}
