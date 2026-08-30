<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Collaboration\DTOs\TimelineItem;
use Illuminate\Support\Carbon;
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

    /**
     * Presentation-only day grouping for the activity timeline. The service keeps
     * producing a flat, ordered list; only the rendering is grouped here.
     *
     * @param  iterable<TimelineItem>  $items
     * @return list<array{label: string, items: list<TimelineItem>}>
     */
    public static function dayGroups(iterable $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            $key = $item->occurredAt->toDateString();
            $groups[$key] ??= ['label' => self::dayLabel($item->occurredAt), 'items' => []];
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    public static function dayLabel(Carbon $moment): string
    {
        return match (true) {
            $moment->isToday() => (string) trans('collaboration.timeline.days.today'),
            $moment->isYesterday() => (string) trans('collaboration.timeline.days.yesterday'),
            default => $moment->translatedFormat('d F Y'),
        };
    }

    /** Note events are the only ones rendered on the highlighted surface. */
    public static function isNote(TimelineItem $item): bool
    {
        return $item->type === 'comment';
    }

    /** Strips the "yorum: \"…\"" wrapper so the note body can stand on its own surface. */
    public static function noteBody(TimelineItem $item): string
    {
        $wrapper = (string) trans('collaboration.activity.comment', ['body' => '__BODY__']);
        [$prefix, $suffix] = array_pad(explode('__BODY__', $wrapper, 2), 2, '');

        $body = $item->sentence;

        if ($prefix !== '' && str_starts_with($body, $prefix)) {
            $body = mb_substr($body, mb_strlen($prefix));
        }

        if ($suffix !== '' && str_ends_with($body, $suffix)) {
            $body = mb_substr($body, 0, mb_strlen($body) - mb_strlen($suffix));
        }

        return $body;
    }

    public static function eventIcon(string $type): string
    {
        return match ($type) {
            'comment' => 'heroicon-o-chat-bubble-left-right',
            'status' => 'heroicon-o-arrow-path',
            'document' => 'heroicon-o-document-text',
            default => 'heroicon-o-bolt',
        };
    }

    public static function eventTone(string $type): string
    {
        return match ($type) {
            'comment' => 'note',
            'status' => 'success',
            'document' => 'info',
            default => 'neutral',
        };
    }
}
