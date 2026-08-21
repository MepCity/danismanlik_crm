<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Schemas\Components\Tabs\Tab;

trait HasZohoListChrome
{
    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make($this->getAllRecordsViewLabel()),
        ];
    }

    /** @param array<int, Action> $actions
     * @return array<int, Action>
     */
    protected function withListChromeActions(array $actions = []): array
    {
        return [
            Action::make('refresh_list')
                ->label(__('panel.list.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(static fn (): null => null),
            ...$actions,
        ];
    }

    protected function getAllRecordsViewLabel(): string
    {
        return __('panel.list.all_records');
    }
}
