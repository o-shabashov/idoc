<?php

namespace OVAC\IDoc\Traits;

use Illuminate\Support\Collection;

trait SplittedRuleValueObject
{
    protected string $formatDescription = '';

    protected function isRequired(Collection $ruleParts): bool
    {
        return $ruleParts->search('required', true) !== false;
    }

    protected function isNullable(Collection $ruleParts): bool
    {
        return $ruleParts->search('nullable', true) !== false;
    }

    protected function getType(?string $type): string
    {
        return match ($type) {
            'int', 'integer', => 'integer',
            'numeric', 'float' => 'number',
            'bool', 'boolean' => 'boolean',
            'array' => 'array',
            default => 'string'
        };
    }

    protected function getDescription(...$attr): string
    {
        return vsprintf($this->formatDescription, $attr);
    }
}
