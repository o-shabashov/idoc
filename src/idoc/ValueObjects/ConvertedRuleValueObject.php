<?php

namespace OVAC\IDoc\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

readonly class ConvertedRuleValueObject implements Arrayable
{
    public function __construct(
        public string $type,
        public string $description,
        public bool $required,
        public mixed $value,
        public bool $nullable,
    ) {
        // Nothing
    }

    public function toArray(): array
    {
        return [
            'type'        => $this->type,
            'description' => $this->description,
            'required'    => $this->required,
            'value'       => $this->value,
            'nullable'    => $this->nullable,
        ];
    }
}
