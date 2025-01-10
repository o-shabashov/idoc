<?php

namespace OVAC\IDoc\Traits;

use Illuminate\Validation\Rules\Enum;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionException;

trait EnumRuleReflector
{
    protected ?ReflectionEnum $enumReflection = null;

    /**
     * @throws ReflectionException
     */
    protected function getEnumValues(Enum $enum): array
    {
        $ruleReflection       = new ReflectionClass($enum);
        $enumClass            = $ruleReflection->getProperty('type')->getValue($enum);
        $this->enumReflection = new ReflectionEnum($enumClass);

        return array_map(
            fn (ReflectionEnumBackedCase|ReflectionEnumUnitCase $enumCase) => $this->getEnumCaseValues($enumCase),
            $this->enumReflection->getCases()
        );
    }

    protected function getEnumCaseValues(ReflectionEnumBackedCase|ReflectionEnumUnitCase $enumCase): mixed
    {
        return $enumCase instanceof ReflectionEnumBackedCase
            ? $enumCase->getBackingValue()
            : $enumCase->getName();
    }

    protected function getNameBackingType(): ?string
    {
        return $this->enumReflection?->getBackingType()?->getName();
    }
}
