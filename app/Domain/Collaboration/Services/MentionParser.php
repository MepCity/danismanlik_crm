<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

final class MentionParser
{
    /** @return list<int> */
    public function userIds(string $body): array
    {
        preg_match_all('/@\[[^\]\r\n]+\]\(user:(\d+)\)/u', $body, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
