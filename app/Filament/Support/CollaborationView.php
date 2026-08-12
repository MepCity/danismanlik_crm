<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class CollaborationView
{
    public static function commentText(string $body): string
    {
        return (string) preg_replace('/@\[([^\]\r\n]+)\]\(user:\d+\)/u', '@$1', $body);
    }
}
