<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/** @implements Rule<Function_> */
final class ForbidCrossModuleModelFunctionTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Function_::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $classes = TypeNodeModelReferences::resolve($node->returnType, $scope);

        foreach ($node->params as $parameter) {
            array_push($classes, ...TypeNodeModelReferences::resolve($parameter->type, $scope));
        }

        $errors = [];

        foreach (array_unique($classes) as $class) {
            array_push($errors, ...CrossModuleModelBoundary::check($scope, $class));
        }

        return $errors;
    }
}
