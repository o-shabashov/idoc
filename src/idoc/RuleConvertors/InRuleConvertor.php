<?php

namespace OVAC\IDoc\RuleConvertors;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use OVAC\IDoc\Traits\EnumRuleReflector;
use OVAC\IDoc\Traits\RuleSplitTrait;
use OVAC\IDoc\Traits\SplittedRuleValueObject;
use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;
use ReflectionClass;
use ReflectionException;

class InRuleConvertor implements RuleConvertor
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
        /** @var In $enum */
        $rulesIn = $ruleParts
            ->where(fn ($part) => is_object($part) && $part::class === In::class)
            ->firstOrFail();
        $values = $this->getValues($rulesIn);

        $required    = $this->isRequired($ruleParts);
        $description = $this->getDescription($values->implode('|'));
        $value       = $values->random();
        $type        = $this->getType(gettype($value));

        $nullable = $this->isNullable($ruleParts);

        return new ConvertedRuleValueObject($type, $description, $required, $value, $nullable);
    }

    /**
     * @throws ReflectionException
     */
    protected function getValues(In|NotIn $rulesIn): Collection
    {
        $inRuleReflection = new ReflectionClass($rulesIn);
        $valuesRaw        = $inRuleReflection->getProperty('values')->getValue($rulesIn);
        $values           = [];
        foreach ($valuesRaw as $value) {
            if ($value instanceof Enum) {
                $values = array_merge($values, $this->getEnumValues($value));

                continue;
            }
            $values[] = $value;
        }

        return collect($values);
    }
}
