<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;

final class StatusBadge
{
    public static function make(string $token, string $label): HtmlString
    {
        $shape = match ($token) {
            'info' => 'i',
            'waiting' => '◷',
            'success' => '✓',
            'danger' => '!',
            default => '•',
        };

        return new HtmlString(sprintf(
            '<span class="status-token" data-status="%s"><span class="status-token__shape" aria-hidden="true">%s</span><span>%s</span></span>',
            e($token),
            e($shape),
            e($label),
        ));
    }
}
