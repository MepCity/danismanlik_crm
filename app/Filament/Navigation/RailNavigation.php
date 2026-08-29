<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;

/**
 * Presentation-only mapping from Filament's already access-filtered
 * navigation (grouped by the existing panel navigation groups) onto the
 * six Cubicl-style rail slots. It never re-implements authorization: a
 * slot is visible only because Filament already decided at least one of
 * its underlying pages/resources is accessible to the current user.
 */
final class RailNavigation
{
    /** @return list<RailItem> */
    public static function build(): array
    {
        /** @var Collection<string, NavigationGroup> $groups */
        $groups = collect(filament()->getNavigation())
            ->keyBy(fn (NavigationGroup $group): string => $group->getLabel() ?? '');

        $items = [
            self::direct(
                key: 'dashboard',
                icon: 'heroicon-o-home',
                group: $groups->get(''),
            ),
            self::flyout(
                key: 'marketing',
                label: __('panel.navigation.rail.marketing'),
                icon: 'heroicon-o-megaphone',
                group: $groups->get(__('panel.navigation.groups.marketing')),
            ),
            self::flyout(
                key: 'companies',
                label: __('panel.navigation.rail.companies'),
                icon: 'heroicon-o-building-office-2',
                group: $groups->get(__('panel.navigation.groups.crm')),
            ),
            self::flyout(
                key: 'files',
                label: __('panel.navigation.rail.files'),
                icon: 'heroicon-o-folder-open',
                group: $groups->get(__('panel.navigation.groups.operations')),
            ),
            self::direct(
                key: 'reports',
                icon: 'heroicon-o-chart-bar-square',
                group: $groups->get(__('panel.navigation.groups.reports')),
            ),
            self::flyout(
                key: 'other',
                label: __('panel.navigation.rail.other'),
                icon: 'heroicon-o-ellipsis-horizontal-circle',
                group: $groups->get(__('panel.navigation.groups.configuration')),
            ),
        ];

        return array_values(array_filter($items));
    }

    private static function direct(string $key, string $icon, ?NavigationGroup $group): ?RailItem
    {
        if ($group === null) {
            return null;
        }

        $navigationItem = collect($group->getItems())->first();

        if (! $navigationItem instanceof NavigationItem) {
            return null;
        }

        $url = $navigationItem->getUrl();

        if ($url === null) {
            return null;
        }

        return new RailItem(
            key: $key,
            label: $navigationItem->getLabel(),
            icon: $icon,
            url: $url,
            active: $navigationItem->isActive(),
            children: [],
        );
    }

    private static function flyout(string $key, string $label, string $icon, ?NavigationGroup $group): ?RailItem
    {
        if ($group === null) {
            return null;
        }

        $children = collect($group->getItems())
            ->map(function (NavigationItem $item): ?array {
                $url = $item->getUrl();

                if ($url === null) {
                    return null;
                }

                return [
                    'label' => $item->getLabel(),
                    'url' => $url,
                    'active' => $item->isActive(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($children === []) {
            return null;
        }

        return new RailItem(
            key: $key,
            label: $label,
            icon: $icon,
            url: null,
            active: collect($children)->contains(fn (array $child): bool => $child['active']),
            children: $children,
        );
    }
}
