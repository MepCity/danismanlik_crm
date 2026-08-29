<?php

declare(strict_types=1);

namespace App\Filament\Navigation;

final class RailItem
{
    /**
     * @param  list<array{label: string, url: string, active: bool}>  $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        public readonly ?string $url,
        public readonly bool $active,
        public readonly array $children,
    ) {}

    public function isFlyout(): bool
    {
        return $this->url === null;
    }
}
