<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmailTemplates;

use App\Domain\Crm\Models\EmailTemplate;
use App\Domain\Crm\Services\EmailTemplateRenderer;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Resources\ScopedResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** @extends ScopedResource<EmailTemplate> */
final class EmailTemplateResource extends ScopedResource
{
    protected static ?string $configurationPermission = 'system.templates';

    protected static ?string $model = EmailTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 32;

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.email_templates.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('marketing.email_templates.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing.email_templates.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('marketing.email_templates.fields.name'))->required()->maxLength(255),
            TextInput::make('subject')->label(__('marketing.email_templates.fields.subject'))->required()->maxLength(255),
            Textarea::make('body')->label(__('marketing.email_templates.fields.body'))->required()->rows(12)->maxLength(10000)->columnSpanFull(),
            Placeholder::make('supported_placeholders')
                ->label(__('marketing.email_templates.supported'))
                ->content(fn (): string => collect(app(EmailTemplateRenderer::class)->supportedPlaceholders())->map(fn (string $label, string $code): string => $code.' — '.$label)->implode("\n"))
                ->columnSpanFull(),
            Toggle::make('is_active')->label(__('management.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('marketing.email_templates.fields.name'))->searchable()->sortable(),
            TextColumn::make('subject')->label(__('marketing.email_templates.fields.subject'))->limit(80),
            IconColumn::make('is_active')->label(__('management.fields.active'))->boolean(),
        ])->recordActions([EditAction::make()->label(__('management.actions.edit'))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/olustur'),
            'edit' => EditEmailTemplate::route('/{record}/duzenle'),
        ];
    }
}
