<?php

namespace OVAC\IDoc\RuleConvertors;

use Illuminate\Validation\Rules\Enum;
use OVAC\IDoc\Traits\EnumRuleReflector;
use OVAC\IDoc\Traits\RuleSplitTrait;
use OVAC\IDoc\Traits\SplittedRuleValueObject;
use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;
use ReflectionException;

class EnumRuleConvertor implements RuleConvertor
{
    use EnumRuleReflector;
    use RuleSplitTrait;
    use SplittedRuleValueObject;

    public function __construct()
    {
        $this->formatDescription = 'One of (%s)';
    }

    /**
     * @throws ReflectionException
     */
    public function convert(array|string|object $rule): ConvertedRuleValueObject
    {
        $ruleParts = $this->spitRule($rule);
        /** @var Enum $enum */
        $enum = $ruleParts
            ->where(fn ($part) => is_object($part) && $part::class === Enum::class)
            ->firstOrFail();
        $enumValues = collect($this->getEnumValues($enum));

        $required    = $this->isRequired($ruleParts);
        $description = $this->getDescription($enumValues->implode('|'));
        $type        = $this->getType($this->getNameBackingType());
        $value       = $enumValues->random();

        $nullable = $this->isNullable($ruleParts);

        return new ConvertedRuleValueObject($type, $description, $required, $value, $nullable);
    }
}
