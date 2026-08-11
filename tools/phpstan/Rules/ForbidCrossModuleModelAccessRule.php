<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Name>
 */
final class ForbidCrossModuleModelAccessRule implements Rule
{
    private const DOMAIN_NAMESPACE = 'App\\Domain\\';

    public function getNodeType(): string
    {
        return Name::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $namespace = $scope->getNamespace();

        if ($namespace === null || ! str_starts_with($namespace, self::DOMAIN_NAMESPACE)) {
            return [];
        }

        $sourceModule = $this->moduleFromNamespace($namespace);
        $referencedClass = $scope->resolveName($node);
        $targetModule = $this->modelModuleFromClass($referencedClass);

        if ($sourceModule === null || $targetModule === null || $sourceModule === $targetModule) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s modülü, %s modülünün Models katmanına doğrudan erişemez; servis veya DTO kullanın.',
                $sourceModule,
                $targetModule,
            ))
                ->identifier('architecture.crossModuleModelAccess')
                ->build(),
        ];
    }

    private function moduleFromNamespace(string $namespace): ?string
    {
        $relativeNamespace = substr($namespace, strlen(self::DOMAIN_NAMESPACE));
        $module = explode('\\', $relativeNamespace, 2)[0];

        return $module !== '' ? $module : null;
    }

    private function modelModuleFromClass(string $class): ?string
    {
        if (preg_match('/^App\\\\Domain\\\\([^\\\\]+)\\\\Models\\\\/', $class, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
