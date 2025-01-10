<?php

namespace OVAC\IDoc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;
use OVAC\IDoc\Tools\ResponseResolver;
use OVAC\IDoc\Tools\Traits\ParamHelpers;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class IDocGenerator
{
    use ParamHelpers;

    public function __construct(
        protected RuleConvertorFactory $ruleConvertorFactory,
        protected DummyValueGenerator $dummyValueGenerator,
        protected ParameterResolver $parameterResolver,
        protected RouteConvertor $routeConvertor
    ) {
        // Nothing
    }

    public function getUri(Route $route): string
    {
        return $route->uri();
    }

    public function getMethods(Route $route): array
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    /**
     * @throws ReflectionException
     */
    public function processRoute(Route $route, array $rulesToApply = []): array
    {
        $queryParameters  = [];
        $bodyParameters   = [];
        $routeAction      = $route->getAction();
        [$class, $method] = explode('@', $routeAction['uses']);
        $controller       = new ReflectionClass($class);
        $method           = $controller->getMethod($method);

        $routeGroup = $this->getRouteGroup($controller, $method);
        $docBlock   = $this->parseDocBlock($method);

        $parameters = $this->convertRouteValidationRules($this->getRouteValidationRules($route));
        $this->parameterResolver->isQueryParameters($method, $route)
            ? $queryParameters = $parameters
            : $bodyParameters  = $parameters;
        $pathParameters        = $this->routeConvertor->convertPathParameters($route, $method);

        $content = ResponseResolver::getResponse($route, $docBlock['tags'], [
            'rules' => $rulesToApply,
            'body'  => $bodyParameters,
            'query' => $queryParameters,
        ], $method);

        $parsedRoute = [
            'id'              => md5($this->getUri($route).':'.implode($this->getMethods($route))),
            'group'           => $routeGroup,
            'title'           => $docBlock['short'] ?: $controller->getShortName().'@'.$method->getName(),
            'description'     => $docBlock['long'],
            'methods'         => $this->getMethods($route),
            'uri'             => $this->getUri($route),
            'bodyParameters'  => $bodyParameters,
            'queryParameters' => $queryParameters,
            'pathParameters'  => $pathParameters,
            'authenticated'   => $authenticated = $this->getAuthStatusFromDocBlock($docBlock['tags']),
            'response'        => $content,
            'showresponse'    => ! empty($content),
        ];

        if (! $authenticated && array_key_exists('Authorization', ($rulesToApply['headers'] ?? []))) {
            unset($rulesToApply['headers']['Authorization']);
        }

        $parsedRoute['headers'] = $rulesToApply['headers'] ?? [];

        return $parsedRoute;
    }

    /**
     * @throws ReflectionException
     */
    protected function getRouteValidationRules($route): array
    {
        $routeAction      = $route->getAction();
        [$class, $method] = explode('@', $routeAction['uses']);

        $reflection       = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $parameter->getType();
            if (! is_null($parameterType) && class_exists($parameterType->getName())) {
                $className = $parameterType->getName();

                if (is_subclass_of($className, FormRequest::class)) {
                    $parameterReflection = new $className;

                    if (method_exists($parameterReflection, 'validator')) {
                        return $parameterReflection->validator()->getRules();
                    }

                    return $parameterReflection->rules();
                }
            }
        }

        return [];
    }

    protected function convertRouteValidationRules($rules): array
    {
        return collect($rules)
            ->mapWithKeys(function ($rule, $name) {
                return [$name => $this
                    ->ruleConvertorFactory
                    ->make($rule)
                    ->convert($rule)
                    ->toArray(),
                ];
            })->toArray();
    }

    protected function getPathParametersFromDocBlock(array $tags): array
    {
        return collect($tags)
            ->filter(function ($tag) {
                return $tag->getName() === 'pathParam';
            })
            ->mapWithKeys($this->parseDocBlock(...))->toArray();
    }

    protected function getBodyParametersFromDocBlock(array $tags): array
    {
        return collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'bodyParam';
            })
            ->mapWithKeys($this->parserTagParam(...))->toArray();
    }

    protected function getAuthStatusFromDocBlock(array $tags): bool
    {
        $authTag = collect($tags)
            ->first(function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'authenticated';
            });

        return (bool) $authTag;
    }

    protected function getRouteGroup(ReflectionClass $controller, ReflectionMethod $method): string
    {
        // @group tag on the method overrides that on the controller
        $docBlockComment = $method->getDocComment();
        if ($docBlockComment) {
            $phpdoc = new DocBlock($docBlockComment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    return $tag->getContent();
                }
            }
        }

        $docBlockComment = $controller->getDocComment();
        if ($docBlockComment) {
            $phpdoc = new DocBlock($docBlockComment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'group') {
                    return $tag->getContent();
                }
            }
        }

        return 'general';
    }

    protected function getQueryParametersFromDocBlock(array $tags): array
    {
        return collect($tags)
            ->filter(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'queryParam';
            })
            ->mapWithKeys($this->parserTagParam(...))->toArray();
    }

    protected function parseDocBlock(ReflectionMethod $method): array
    {
        $comment = $method->getDocComment();
        $phpdoc  = new DocBlock($comment);

        return [
            'short' => $phpdoc->getShortDescription(),
            'long'  => $phpdoc->getLongDescription()->getContents(),
            'tags'  => $phpdoc->getTags(),
        ];
    }

    private function normalizeParameterType($type): string
    {
        $typeMap = [
            'int'    => 'integer',
            'bool'   => 'boolean',
            'double' => 'float',
        ];

        return $type ? ($typeMap[$type] ?? $type) : 'string';
    }

    /**
     * Allows users to specify an example for the parameter by writing 'Example: the-example',
     * to be used in example requests and response calls.
     *
     * @param  string  $type  The type of the parameter. Used to cast the example provided, if any.
     * @return array The description and included example.
     */
    private function parseDescription(string $description, string $type): array
    {
        $example = null;
        if (preg_match('/(.*)\s+Example:\s*(.*)\s*/', $description, $content)) {
            $description = $content[1];

            // examples are parsed as strings by default, we need to cast them properly
            $example = $this->castToType($content[2], $type);
        }

        return [$description, $example];
    }

    /**
     * Cast a value from a string to a specified type.
     */
    private function castToType(string $value, string $type): string|bool
    {
        $casts = [
            'integer' => 'intval',
            'number'  => 'floatval',
            'float'   => 'floatval',
            'boolean' => 'boolval',
        ];

        // First, we handle booleans. We can't use a regular cast,
        //because PHP considers string 'false' as true.
        if ($value == 'false' && $type == 'boolean') {
            return false;
        }

        if (isset($casts[$type])) {
            return $casts[$type]($value);
        }

        return $value;
    }

    private function parserTagParam($tag): array
    {
        preg_match('/(.+?)\s+(.+?)\s+(required\s+)?(.*)/', $tag->getContent(), $content);
        if (empty($content)) {
            // this means only name and type were supplied
            [$name, $type] = preg_split('/\s+/', $tag->getContent());
            $required      = false;
            $description   = '';
        } else {
            [$_, $name, $type, $required, $description] = $content;
            $description                                = trim($description);
            if ($description == 'required' && empty(trim($required))) {
                $required    = $description;
                $description = '';
            }
            $required = trim($required) == 'required';
        }

        $type                    = $this->normalizeParameterType($type);
        [$description, $example] = $this->parseDescription($description, $type);
        $value                   = is_null($example) ? $this->dummyValueGenerator->generate($type) : $example;

        return [$name => compact('type', 'description', 'required', 'value')];
    }
}
