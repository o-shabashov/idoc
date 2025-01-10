<?php

namespace OVAC\IDoc\RuleConvertors;

use Illuminate\Validation\Rules\NotIn;
use OVAC\IDoc\Traits\EnumRuleReflector;
use OVAC\IDoc\Traits\RuleSplitTrait;
use OVAC\IDoc\Traits\SplittedRuleValueObject;
use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;
use ReflectionException;

class NotInRuleConvertor extends InRuleConvertor
{
    use EnumRuleReflector;
    use RuleSplitTrait;
    use SplittedRuleValueObject;

    public function __construct()
    {
        parent::__construct();
        $this->formatDescription = 'Anyone except (%s)';
    }

    /**
     * @throws ReflectionException
     */
    public function convert(array|string|object $rule): ConvertedRuleValueObject
    {
        $ruleParts = $this->spitRule($rule);
        /** @var NotIn $enum */
        $rulesNotIn = $ruleParts
            ->where(fn ($part) => is_object($part) && $part::class === NotIn::class)
            ->firstOrFail();
        $values = $this->getValues($rulesNotIn);

        $required    = $this->isRequired($ruleParts);
        $description = $this->getDescription($values->implode('|'));
        $value       = '';
        $type        = $this->getType(gettype($values->random()));

        $nullable = $this->isNullable($ruleParts);

        return new ConvertedRuleValueObject($type, $description, $required, $value, $nullable);
    }
}
