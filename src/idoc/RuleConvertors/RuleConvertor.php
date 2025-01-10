<?php

namespace OVAC\IDoc\RuleConvertors;

use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;

interface RuleConvertor
{
    public function convert(array|string|object $rule): ConvertedRuleValueObject;
}
