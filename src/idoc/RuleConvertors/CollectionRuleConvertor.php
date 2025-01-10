<?php

namespace OVAC\IDoc\RuleConvertors;

use Illuminate\Support\Collection;
use OVAC\IDoc\DummyValueGenerator;
use OVAC\IDoc\Traits\RuleSplitTrait;
use OVAC\IDoc\Traits\SplittedRuleValueObject;
use OVAC\IDoc\ValueObjects\ConvertedRuleValueObject;

class CollectionRuleConvertor implements RuleConvertor
{
    use RuleSplitTrait;
    use SplittedRuleValueObject;

    public function __construct(protected DummyValueGenerator $dummyValueGenerator)
    {
    }

    private array $availableTypes = [
        'integer', 'int', 'numeric', 'float', 'boolean', 'bool', 'string', 'array', 'json', 'image',
        'date', 'email', 'url', 'ulid', 'uuid',
    ];

    private array $availableDescription = [
        'array:' => 'Must have the keys (%s).',
        'date'   => 'Date.',
    ];

    private array $availableFormat = [
        'in:',
        'date_format:',
        'email',
        'url',
        'ulid',
        'uuid',
    ];

    public function convert(array|string|object $rule): ConvertedRuleValueObject
    {
        $ruleParts = $this->spitRule($rule);

        $required      = $this->isRequired($ruleParts);
        $description   = $this->getDescription($this->getAvailableDescription($ruleParts));
        $availableType = $this->getAvailableTypes($ruleParts);
        $type          = $this->getType($availableType);
        $format        = $this->getAvailableFormat($ruleParts);
        if ($format) {
            $description = trim($description." Format: $format.");
        }
        $value    = is_string($availableType) ? $this->dummyValueGenerator->generate($availableType, $format) : 'word';
        $nullable = $this->isNullable($ruleParts);

        return new ConvertedRuleValueObject($type, $description, $required, $value, $nullable);
    }

    private function getAvailableTypes(Collection $ruleParts): ?string
    {
        $collection = $ruleParts->filter(function ($item) {
            if (in_array($item, $this->availableTypes)) {
                return true;
            }

            return array_filter($this->availableTypes, function ($key) use ($item) {
                return str_contains($item, $key);
            });
        })->first();

        return $collection ? strstr($collection, ':', true) ?: $collection : null;
    }

    private function getAvailableDescription(Collection $ruleParts): ?string
    {
        $collection = $ruleParts->filter(function ($item) {
            return array_filter($this->availableDescription, function ($key) use ($item) {
                if (str_contains($item, $key)) {
                    $this->formatDescription = $this->availableDescription[$key];

                    return true;
                }

                return false;
            }, ARRAY_FILTER_USE_KEY);
        })->first();

        return $collection
            ? str_replace(',', '|', ltrim(strstr($collection, ':'), ':'))
            : null;
    }

    private function getAvailableFormat(Collection $ruleParts): ?string
    {
        $collection = $ruleParts->filter(function ($item) {
            return array_filter($this->availableFormat, function ($key) use ($item) {
                if (in_array($item, $this->availableFormat)) {
                    return true;
                }

                return str_contains($item, $key);
            });
        })->first();

        return $collection ? str_contains($collection, ':') ? ltrim(strstr($collection, ':'), ':') : $collection : null;
    }
}
