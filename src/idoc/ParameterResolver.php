<?php

namespace OVAC\IDoc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use OVAC\IDoc\Tools\Attributes\QueryType;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class ParameterResolver
{
    /**
     * @throws ReflectionException
     */
    public function isQueryParameters(ReflectionMethod $method, Route $route): bool
    {
        $parameters = $method->getParameters();
        foreach ($parameters as $parameter) {
            if (is_subclass_of($parameter->getType()?->getName(), FormRequest::class)) {
                $request = new ReflectionClass($parameter->getType()?->getName());
                foreach ($request->getAttributes() as $attribute) {
                    if ($instance = $attribute->newInstance()) {
                        return $instance instanceof QueryType;
                    }
                }
            }
        }

        return in_array('GET', $route->methods) || in_array('get', $route->methods);
    }
}
