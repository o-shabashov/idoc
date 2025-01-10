<?php

namespace OVAC\IDoc\Tools;

use Illuminate\Routing\Route;
use OVAC\IDoc\Tools\ResponseStrategies\ResourceCollectionStrategy;
use OVAC\IDoc\Tools\ResponseStrategies\ResourceStrategy;
use OVAC\IDoc\Tools\ResponseStrategies\ResponseCallStrategy;
use OVAC\IDoc\Tools\ResponseStrategies\ResponseFileStrategy;
use OVAC\IDoc\Tools\ResponseStrategies\ResponseTagStrategy;
use OVAC\IDoc\Tools\ResponseStrategies\TransformerTagsStrategy;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

class ResponseResolver
{
    public static array $strategies = [
        ResponseTagStrategy::class,
        TransformerTagsStrategy::class,
        ResponseFileStrategy::class,
        ResponseCallStrategy::class,
        ResourceStrategy::class,
        ResourceCollectionStrategy::class,
    ];

    private Route $route;

    public function __construct(Route $route)
    {
        $this->route = $route;
    }

    private function resolve(array $tags, array $routeProps, ReflectionMethod $method): ?array
    {
        foreach (static::$strategies as $strategy) {
            $strategy = new $strategy();

            /** @var Response[]|null $response */
            $responses = $strategy($this->route, $tags, $routeProps, $method);

            if (! is_null($responses)) {
                return array_map(function (Response $response) {
                    return ['status' => $response->getStatusCode(), 'content' => $this->getResponseContent($response)];
                }, $responses);
            }
        }

        return null;
    }

    public static function getResponse($route, $tags, $routeProps, $method): ?array
    {
        return (new static($route))->resolve($tags, $routeProps, $method);
    }

    private function getResponseContent(mixed $response): mixed
    {
        return $response ? $response->getContent() : '';
    }
}
