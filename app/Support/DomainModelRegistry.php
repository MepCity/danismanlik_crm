<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class DomainModelRegistry
{
    /** @return class-string<Model> */
    public static function resolve(string $key): string
    {
        $model = config("domain-models.{$key}");

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new LogicException("Geçersiz domain model kaydı: {$key}");
        }

        return $model;
    }
}
