<?php

namespace OVAC\IDoc\Tools\ResponseStrategies;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use OVAC\IDoc\Tools\ResponseStrategies\Resource\ResourceExampleInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Get a response from the return.
 */
class ResourceStrategy
{
    /**
     * @throws ReflectionException
     */
    public function __invoke(Route $route, array $tags, array $routeProps, ReflectionMethod $method): ?array
    {
        return $this->getResource($method);
    }

    /**
     * Get the response from the return if available.
     *
     *
     * @throws ReflectionException
     */
    protected function getResource(ReflectionMethod $method): ?array
    {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionUnionType) {
            $result = array_map(function (ReflectionNamedType $returnNameType) {
                return $this->getResponse($returnNameType);
            }, $returnType->getTypes());

            return array_diff($result, [null]);
        }

        if ($returnType instanceof ReflectionNamedType) {
            $result = $this->getResponse($returnType);

            return $result ? [$result] : null;
        }

        return null;
    }

    /**
     * @throws ReflectionException
     */
    private function getResponse(ReflectionNamedType $returnNameType): ?JsonResponse
    {
        if ($returnNameType->getName() && class_exists($returnNameType->getName())) {
            $returnTypeReflection = new ReflectionClass($returnNameType->getName());

            if ($returnTypeReflection->implementsInterface(ResourceExampleInterface::class)) {
                /** @var ResourceExampleInterface $resourceObj */
                $resource = $returnTypeReflection->newInstance(null);

                return new JsonResponse($resource->toExampleArray(), $resource->getCode());
            }

            return null;
        }

        return null;
    }
}
