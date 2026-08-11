<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/** @implements Rule<Property> */
final class ForbidCrossModuleModelPropertyTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach (TypeNodeModelReferences::resolve($node->type, $scope) as $class) {
            array_push($errors, ...CrossModuleModelBoundary::check($scope, $class));
        }

        return $errors;
    }
}
