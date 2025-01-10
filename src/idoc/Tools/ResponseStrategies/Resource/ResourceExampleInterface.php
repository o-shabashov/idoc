<?php

namespace OVAC\IDoc\Tools\ResponseStrategies\Resource;

interface ResourceExampleInterface
{
    public function getCode(): int;

    public function toExampleArray(): array;
}
