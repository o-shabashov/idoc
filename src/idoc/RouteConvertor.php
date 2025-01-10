<?php

namespace OVAC\IDoc;

use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use ReflectionMethod;

class RouteConvertor
{
    public function __construct(protected DummyValueGenerator $dummyValueGenerator)
    {
    }

    public function convertPathParameters(Route $route, ?ReflectionMethod $method = null): array
    {
        $parameters = [];
        preg_match_all('/\{(.*?)\}/', $route->getDomain().$route->uri(), $matches);
        if ($pathParameters = $matches[1]) {
            foreach ($pathParameters as $pathParameterRaw) {
                $pathParameter = explode('?', $pathParameterRaw);
                $name          = $this->getParsedName($pathParameter[0]);
                $regex         = $route->wheres[$name] ?? '';

                $value = match ($regex) {
                    '[0-9]+' => $this->dummyValueGenerator->generate('integer'),
                    default  => $this->dummyValueGenerator->generate('regex', $regex),
                };
                $value       = $value ?: $this->dummyValueGenerator->generate('string');
                $type        = gettype($value);
                $required    = ! array_key_exists(1, $pathParameter);
                $description = $method ? $this->getParamDescription($name, $method) : '';

                $parameters[$name] = compact('type', 'description', 'required', 'value');
            }
        }

        return $parameters;
    }

    private function getParamDescription(string $name, ReflectionMethod $method): string
    {
        $description     = '';
        $variableName    = "$$name";
        $docBlockComment = $method->getDocComment();
        if ($docBlockComment) {
            $phpdoc = new DocBlock($docBlockComment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'param' && $tag->getVariableName() === $variableName) {
                    $description = $tag->getDescription();
                }
            }
        }

        return $description;
    }

    private function getParsedName(string $name): string
    {
        return strstr($name, ':', true) ?: $name;
    }
}
