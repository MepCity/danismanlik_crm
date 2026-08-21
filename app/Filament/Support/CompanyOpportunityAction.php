<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Crm\Actions\CreateCompanyOpportunity;
use App\Domain\Crm\Models\Company;
use App\Domain\Program\Services\ActiveProgramVersionReader;
use App\Filament\Pages\LeadDetail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Livewire\Component;

final class CompanyOpportunityAction
{
    public static function make(): Action
    {
        return Action::make('create_opportunity')
            ->label(__('marketing.company_opportunity.action'))
            ->icon('heroicon-o-phone-arrow-up-right')
            ->visible(fn (Company $record): bool => $record->is_active && auth()->user()?->can('lead.manage') === true)
            ->modalHeading(__('marketing.company_opportunity.heading'))
            ->modalDescription(__('marketing.company_opportunity.description'))
            ->modalSubmitActionLabel(__('marketing.company_opportunity.submit'))
            ->form([
                Select::make('program_version_id')
                    ->label(__('marketing.detail.program'))
                    ->options(function (): array {
                        $options = [];
                        foreach (app(ActiveProgramVersionReader::class)->all() as $version) {
                            $options[$version->id] = $version->label();
                        }

                        return $options;
                    })
                    ->searchable()
                    ->required(),
                Select::make('contact_id')
                    ->label(__('marketing.company_opportunity.contact'))
                    ->options(fn (Company $record): array => $record->contacts()->where('is_active', true)->orderBy('full_name')->pluck('full_name', 'id')->all())
                    ->searchable(),
                DateTimePicker::make('next_call_at')
                    ->label(__('marketing.company_opportunity.next_call_at'))
                    ->native(false)
                    ->minDate(now()),
            ])
            ->action(function (Company $record, array $data, Component $livewire): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $lead = app(CreateCompanyOpportunity::class)->execute(
                    $record->id,
                    (int) $data['program_version_id'],
                    $actor,
                    filled($data['contact_id'] ?? null) ? (int) $data['contact_id'] : null,
                    filled($data['next_call_at'] ?? null) ? (string) $data['next_call_at'] : null,
                );
                Notification::make()->title(__('marketing.company_opportunity.created'))->success()->send();
                $livewire->redirect(LeadDetail::getUrl(['lead' => $lead->id]), navigate: true);
            });
    }
}
