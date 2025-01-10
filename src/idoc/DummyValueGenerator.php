<?php

namespace OVAC\IDoc;

class DummyValueGenerator
{
    public function generate(string $type, ?string $format = null): mixed
    {
        return match ($type) {
            'integer', 'int' => 10,
            'numeric', 'float' => '10.5',
            'boolean', 'bool' => true,
            'array' => '[]',
            'json'  => '{}',
            'image' => fake()->image,
            'date'  => $format ? now()->format($format) : '',
            'regex' => $format ? fake()->regexify($format) : '',
            'url'   => 'https://searchanise.io',
            'email' => 'test@searchanise.io',
            'uuid'  => '550e8400-e29b-41d4-a716-446655440000',
            'ulid'  => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            default => $format ? $this->getStringForFormat($format) : 'word'
        };
    }

    private function getStringForFormat(string $format): string
    {
        $inFormat = explode(',', $format);

        return array_key_exists(0, $inFormat) ? $inFormat[0] : 'word';
    }
}
