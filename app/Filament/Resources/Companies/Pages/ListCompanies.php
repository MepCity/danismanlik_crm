<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Services\BulkCompanyEmailService;
use App\Domain\Crm\Services\EmailTemplateRenderer;
use App\Filament\Concerns\HasZohoListChrome;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ListCompanies extends ListRecords
{
    use HasZohoListChrome;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return $this->withListChromeActions([
            Action::make('sort_list')
                ->label(__('panel.list.sort'))
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'column' => $this->getTableSortColumn() ?: 'legal_name',
                    'direction' => $this->getTableSortDirection() ?: 'asc',
                ])
                ->form([
                    Select::make('column')
                        ->label(__('panel.list.sort_by'))
                        ->options([
                            'legal_name' => __('panel.fields.legal_name'),
                            'industry' => __('panel.fields.industry'),
                            'city' => __('panel.fields.city'),
                        ])
                        ->required(),
                    Select::make('direction')
                        ->label(__('panel.list.sort_direction'))
                        ->options([
                            'asc' => __('panel.list.ascending'),
                            'desc' => __('panel.list.descending'),
                        ])
                        ->required(),
                ])
                ->modalSubmitActionLabel(__('panel.list.apply_sort'))
                ->action(fn (array $data): null => $this->applyListSort($data)),
            Action::make('send_filtered_email')
                ->label(__('panel.company_directory.bulk_email.action'))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (): bool => Auth::user()?->can('company.bulk_email') === true)
                ->modalHeading(__('panel.company_directory.bulk_email.heading'))
                ->modalDescription(__('panel.company_directory.bulk_email.description'))
                ->fillForm(fn (): array => [
                    'company_ids' => $this->filteredCompanyIds(),
                    'subject' => '',
                    'body' => '',
                ])
                ->form([
                    Select::make('company_ids')
                        ->label(__('panel.company_directory.bulk_email.companies'))
                        ->helperText(__('panel.company_directory.bulk_email.companies_help'))
                        ->options(fn (): array => $this->companyOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Placeholder::make('selected_count')
                        ->label(__('panel.company_directory.bulk_email.selected_count_label'))
                        ->content(fn (Get $get): string => __('panel.company_directory.bulk_email.selected_count', [
                            'count' => count((array) $get('company_ids')),
                        ])),
                    TextInput::make('subject')
                        ->label(__('panel.company_directory.bulk_email.subject'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    Textarea::make('body')
                        ->label(__('panel.company_directory.bulk_email.body'))
                        ->required()
                        ->rows(10)
                        ->maxLength(10000)
                        ->live(onBlur: true),
                    Placeholder::make('supported_placeholders')
                        ->label(__('marketing.email_templates.supported'))
                        ->content(fn (): string => collect(app(EmailTemplateRenderer::class)->supportedPlaceholders())
                            ->map(fn (string $label, string $code): string => $code.' — '.$label)
                            ->implode("\n")),
                    Placeholder::make('preview')
                        ->label(__('panel.company_directory.bulk_email.preview'))
                        ->content(fn (Get $get): string => $this->emailPreview(
                            (array) $get('company_ids'),
                            (string) $get('subject'),
                            (string) $get('body'),
                        )),
                ])
                ->modalSubmitActionLabel(__('panel.company_directory.bulk_email.send'))
                ->action(fn (array $data, BulkCompanyEmailService $emails): null => $this->sendFilteredEmail($data, $emails)),
            CreateAction::make()->label(__('panel.company_directory.create')),
        ]);
    }

    /** @param array{column: string, direction: string} $data */
    private function applyListSort(array $data): null
    {
        $this->sortTable($data['column'], $data['direction']);

        return null;
    }

    public function getSubheading(): string
    {
        return __('panel.company_directory.description');
    }

    protected function getAllRecordsViewLabel(): string
    {
        return __('panel.list.all_companies');
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('panel.list.all_companies')),
            'active' => Tab::make(__('panel.list.active_companies'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_active', true)),
            'directory_only' => Tab::make(__('panel.list.directory_only'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDoesntHave('deals')),
        ];
    }

    /** @return array<string, mixed> */
    public function filterSnapshot(): array
    {
        return array_filter([
            'view' => $this->activeTab,
            'search' => $this->getTableSearch(),
            'industry' => $this->getTableFilterState('industry'),
            'city' => $this->getTableFilterState('city'),
            'size' => $this->getTableFilterState('size'),
            'is_active' => $this->getTableFilterState('is_active'),
            'customer_state' => $this->getTableFilterState('customer_state'),
        ], static fn (mixed $value): bool => filled($value));
    }

    /** @return list<int> */
    private function filteredCompanyIds(): array
    {
        return $this->getFilteredTableQuery()
            ?->reorder()
            ->pluck('companies.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all() ?? [];
    }

    /** @return array<int, string> */
    private function companyOptions(): array
    {
        return CompanyResource::getEloquentQuery()
            ->orderBy('legal_name')
            ->pluck('legal_name', 'id')
            ->all();
    }

    /** @param list<int|string> $companyIds */
    private function emailPreview(array $companyIds, string $subject, string $body): string
    {
        if ($companyIds === [] || blank($subject) || blank($body)) {
            return __('panel.company_directory.bulk_email.preview_empty');
        }

        $allowedCompanyIds = CompanyResource::getEloquentQuery()
            ->whereIn('companies.id', array_map('intval', $companyIds))
            ->pluck('companies.id');
        $contact = Contact::query()
            ->whereIn('company_id', $allowedCompanyIds)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->with('company')
            ->first();

        if (! $contact instanceof Contact) {
            return __('panel.company_directory.bulk_email.preview_missing');
        }

        try {
            $rendered = app(EmailTemplateRenderer::class)->renderContent($subject, $body, $contact);
        } catch (ValidationException) {
            return __('panel.company_directory.bulk_email.preview_invalid');
        }

        return __('panel.company_directory.bulk_email.preview_text', [
            'recipient' => $contact->full_name,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
        ]);
    }

    /** @param array{company_ids: list<int|string>, subject: string, body: string} $data */
    private function sendFilteredEmail(array $data, BulkCompanyEmailService $emails): null
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        /** @var Collection<int, Company> $companies */
        $companies = CompanyResource::getEloquentQuery()
            ->whereIn('companies.id', array_map('intval', $data['company_ids']))
            ->get();

        if ($companies->isEmpty()) {
            throw ValidationException::withMessages([
                'company_ids' => __('panel.company_directory.bulk_email.companies_required'),
            ]);
        }

        $result = $emails->sendComposed(
            $companies,
            $data['subject'],
            $data['body'],
            $this->filterSnapshot(),
            $actor,
        );

        Notification::make()
            ->title(__('panel.company_directory.bulk_email.result', [
                'queued' => $result->queuedCount,
                'rejected' => $result->consentRejectedCount,
                'missing' => $result->missingEmailCount,
            ]))
            ->success()
            ->send();

        return null;
    }
}
