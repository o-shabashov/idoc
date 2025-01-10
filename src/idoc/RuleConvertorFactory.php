<?php

namespace OVAC\IDoc;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use OVAC\IDoc\RuleConvertors\CollectionRuleConvertor;
use OVAC\IDoc\RuleConvertors\EnumRuleConvertor;
use OVAC\IDoc\RuleConvertors\FileRuleConvertor;
use OVAC\IDoc\RuleConvertors\InRuleConvertor;
use OVAC\IDoc\RuleConvertors\NotInRuleConvertor;
use OVAC\IDoc\RuleConvertors\RuleConvertor;
use OVAC\IDoc\Traits\RuleSplitTrait;

class RuleConvertorFactory
{
    use RuleSplitTrait;

    /**
     * @throws BindingResolutionException
     */
    public function make(array|string|object $rule): RuleConvertor
    {
        $object = $this
            ->spitRule($rule)
            ->filter(fn (mixed $part) => is_object($part))
            ->first();
        $type = $object ? get_class($object) : null;

        return match ($type) {
            Enum::class  => app()->make(EnumRuleConvertor::class),
            In::class    => app()->make(InRuleConvertor::class),
            NotIn::class => app()->make(NotInRuleConvertor::class),
            File::class  => app()->make(FileRuleConvertor::class),
            default      => app()->make(CollectionRuleConvertor::class)
        };
    }
}
