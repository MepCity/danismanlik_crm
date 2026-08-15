<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class CollaborationView
{
    public static function commentText(string $body): string
    {
        return (string) preg_replace('/@\[([^\]\r\n]+)\]\(user:\d+\)/u', '@$1', $body);
    }

    public static function commentHtml(string $body): HtmlString
    {
        $escaped = e($body);
        $html = preg_replace(
            '/@\[([^\]\r\n]+)\]\(user:\d+\)/u',
            '<span class="comment-mention">@$1</span>',
            $escaped,
        );

        return new HtmlString(nl2br($html ?? $escaped));
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[count($parts) - 1] ?? '';

        return Str::upper(Str::substr($first, 0, 1).($last !== $first ? Str::substr($last, 0, 1) : ''));
    }
}
