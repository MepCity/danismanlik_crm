<?php

declare(strict_types=1);

namespace Quality\PHPStan\Rules;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

final class CrossModuleModelBoundary
{
    private const DOMAIN_NAMESPACE = 'App\\Domain\\';

    /** @return list<IdentifierRuleError> */
    public static function check(Scope $scope, string $referencedClass): array
    {
        $namespace = $scope->getNamespace();

        if ($namespace === null || ! str_starts_with($namespace, self::DOMAIN_NAMESPACE)) {
            return [];
        }

        if (preg_match('/^App\\\\Domain\\\\[^\\\\]+\\\\Models(?:\\\\|$)/', $namespace) === 1) {
            return [];
        }

        $sourceModule = self::moduleFromNamespace($namespace);
        $targetModule = self::modelModuleFromClass($referencedClass);

        if ($sourceModule === null || $targetModule === null || $sourceModule === $targetModule) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s modülünün iş mantığı, %s modülünün modeline doğrudan erişemez; servis veya DTO kullanın.',
                $sourceModule,
                $targetModule,
            ))
                ->identifier('architecture.crossModuleModelAccess')
                ->build(),
        ];
    }

    private static function moduleFromNamespace(string $namespace): ?string
    {
        $relativeNamespace = substr($namespace, strlen(self::DOMAIN_NAMESPACE));
        $module = explode('\\', $relativeNamespace, 2)[0];

        return $module !== '' ? $module : null;
    }

    private static function modelModuleFromClass(string $class): ?string
    {
        if (preg_match('/^App\\\\Domain\\\\([^\\\\]+)\\\\Models\\\\/', $class, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
