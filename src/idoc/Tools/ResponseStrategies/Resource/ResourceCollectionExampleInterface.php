<?php

namespace OVAC\IDoc\Tools\ResponseStrategies\Resource;

interface ResourceCollectionExampleInterface
{
    public function getCode(): int;

    public function toExampleArray(): array;
}
