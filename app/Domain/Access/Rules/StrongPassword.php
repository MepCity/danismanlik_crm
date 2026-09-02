<?php

declare(strict_types=1);

namespace App\Domain\Access\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;

final class StrongPassword implements ValidationRule
{
    public static function isValid(string $password): bool
    {
        return mb_strlen($password) >= 12
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[\W_]/', $password) === 1;
    }

    public static function ensureValid(string $password): void
    {
        if (! self::isValid($password)) {
            throw new InvalidArgumentException(__('management.validation.password_strong'));
        }
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('management.validation.password_strong')->translate();
        }
    }
}
