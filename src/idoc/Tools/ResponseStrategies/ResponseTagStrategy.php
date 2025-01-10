<?php

namespace OVAC\IDoc\Tools\ResponseStrategies;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock\Tag;
use ReflectionMethod;

/**
 * Get a response from the docblock ( @response ).
 */
class ResponseTagStrategy
{
    /**
     * @return array|null
     */
    public function __invoke(Route $route, array $tags, array $routeProps, ReflectionMethod $method)
    {
        return $this->getDocBlockResponses($tags);
    }

    /**
     * Get the response from the docblock if available.
     *
     *
     * @return array|null
     */
    protected function getDocBlockResponses(array $tags)
    {
        $responseTags = array_values(
            array_filter($tags, function ($tag) {
                return $tag instanceof Tag && strtolower($tag->getName()) === 'response';
            })
        );

        if (empty($responseTags)) {
            return;
        }

        return array_map(function (Tag $responseTag) {
            preg_match('/^(\d{3})?\s?([\s\S]*)$/', $responseTag->getContent(), $result);

            $status  = $result[1] ?: 200;
            $content = $result[2] ?: '{}';

            return new JsonResponse(json_decode($content, true), (int) $status);
        }, $responseTags);
    }
}
