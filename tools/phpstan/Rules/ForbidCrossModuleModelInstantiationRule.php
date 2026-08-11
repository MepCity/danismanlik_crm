<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/** @implements Rule<New_> */
final class ForbidCrossModuleModelInstantiationRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->class instanceof Name) {
            return [];
        }

        return CrossModuleModelBoundary::check($scope, $scope->resolveName($node->class));
    }
}
