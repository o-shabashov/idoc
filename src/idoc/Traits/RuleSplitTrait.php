<?php

namespace OVAC\IDoc\Traits;

use Illuminate\Support\Collection;

trait RuleSplitTrait
{
    protected function spitRule(array|string|object $rule): Collection
    {
        $ruleParts = is_string($rule) ? explode('|', $rule) : $rule;

        return collect(is_array($ruleParts) ? $ruleParts : [$ruleParts])
            ->filter(fn ($part) => ! ($part instanceof \Closure));
    }
}
