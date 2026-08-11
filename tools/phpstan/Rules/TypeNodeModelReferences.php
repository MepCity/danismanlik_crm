<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPStan\Analyser\Scope;

final class TypeNodeModelReferences
{
    /**
     * @param  ComplexType|Identifier|Name|null  $type
     * @return list<string>
     */
    public static function resolve(?Node $type, Scope $scope): array
    {
        if ($type instanceof Name) {
            return [$scope->resolveName($type)];
        }

        if ($type instanceof NullableType) {
            return self::resolve($type->type, $scope);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $classes = [];

            foreach ($type->types as $member) {
                array_push($classes, ...self::resolve($member, $scope));
            }

            return array_values(array_unique($classes));
        }

        return [];
    }
}
