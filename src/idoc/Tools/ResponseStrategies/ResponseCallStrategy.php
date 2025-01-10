<?php

namespace OVAC\IDoc\Tools\ResponseStrategies;

use Exception;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use OVAC\IDoc\Tools\Traits\ParamHelpers;
use ReflectionMethod;

/**
 * Make a call to the route and retrieve its response.
 */
class ResponseCallStrategy
{
    use ParamHelpers;

    /**
     * @var array|mixed
     */
    private mixed $rulesToApply;

    /**
     * @return array|JsonResponse[]|Response[]|\Symfony\Component\HttpFoundation\Response[]|null
     */
    public function __invoke(Route $route, array $tags, array $routeProps, ReflectionMethod $method): ?array
    {
        $rulesToApply = $routeProps['rules']['response_calls'] ?? [];

        $this->rulesToApply = $rulesToApply;

        if (! $this->shouldMakeApiCall($route, $rulesToApply)) {
            return null;
        }

        $this->configureEnvironment($rulesToApply);
        $request = $this->prepareRequest($route, $rulesToApply, $routeProps['body'], $routeProps['query']);

        try {
            $response = [$this->makeApiCall($request)];
        } catch (Exception $e) {
            $response = null;
        } finally {
            $this->finish();
        }

        return $response;
    }

    private function configureEnvironment(array $rulesToApply): void
    {
        $this->startDbTransaction();
        $this->setEnvironmentVariables($rulesToApply['env'] ?? []);
    }

    private function prepareRequest(Route $route, array $rulesToApply, array $bodyParams, array $queryParams): Request
    {
        $uri          = $this->replaceUrlParameterBindings($route, $rulesToApply['bindings'] ?? []);
        $routeMethods = $this->getMethods($route);
        $method       = array_shift($routeMethods);
        $cookies      = $rulesToApply['cookies'] ?? [];

        // Mix in parsed parameters with manually specified parameters.
        $queryParams = collect($this->cleanParams($queryParams))->merge($rulesToApply['query'] ?? [])->toArray();
        $bodyParams  = collect($this->cleanParams($bodyParams))->merge($rulesToApply['body'] ?? [])->toArray();

        $request = Request::create($uri, $method, [], $cookies, [], $this->transformHeadersToServerVars($rulesToApply['headers'] ?? []), json_encode($bodyParams));
        $request = $this->addHeaders($request, $route, $rulesToApply['headers'] ?? []);
        $request = $this->addQueryParameters($request, $queryParams);

        return $this->addBodyParameters($request, $bodyParams);

    }

    /**
     * Transform parameters in URLs into real values (/users/{user} -> /users/2).
     * Uses bindings specified by caller, otherwise just uses '1'.
     */
    protected function replaceUrlParameterBindings(Route $route, array $bindings): string|array|null
    {
        $uri = $route->uri();
        foreach ($bindings as $parameter => $binding) {
            $uri = str_replace($parameter, $binding, $uri);
        }

        // Replace any unbound parameters with '1'
        return preg_replace('/{(.*?)}/', 1, $uri);
    }

    private function setEnvironmentVariables(array $env): void
    {
        foreach ($env as $name => $value) {
            putenv("$name=$value");

            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }

    private function startDbTransaction(): void
    {
        try {
            app('db')->beginTransaction();
        } catch (Exception $e) {
        }
    }

    private function endDbTransaction(): void
    {
        try {
            app('db')->rollBack();
        } catch (Exception $e) {
        }
    }

    private function finish(): void
    {
        $this->endDbTransaction();
    }

    /**
     * @return JsonResponse|mixed
     */
    public function callDingoRoute(Request $request): mixed
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = app(\Dingo\Api\Dispatcher::class);

        foreach ($request->headers as $header => $value) {
            $dispatcher->header($header, $value);
        }

        // set domain and body parameters
        $dispatcher->on($request->header('SERVER_NAME'))
            ->with($request->request->all());

        // set URL and query parameters
        $uri   = $request->getRequestUri();
        $query = $request->getQueryString();
        if (! empty($query)) {
            $uri .= "?$query";
        }
        $response = call_user_func_array([$dispatcher, strtolower($request->method())], [$uri]);

        // the response from the Dingo dispatcher is the 'raw' response from the controller,
        // so we have to ensure it's JSON first
        if (! $response instanceof Response) {
            $response = response()->json($response);
        }

        return $response;
    }

    public function getMethods(Route $route): array
    {
        return array_diff($route->methods(), ['HEAD']);
    }

    private function addHeaders(Request $request, Route $route, array $headers): Request
    {
        // set the proper domain
        if ($route->getDomain()) {
            $request->server->add([
                'HTTP_HOST'   => $route->getDomain(),
                'SERVER_NAME' => $route->getDomain(),
            ]);
        }

        $headers = collect($headers);

        if (($headers->get('Accept') ?: $headers->get('accept')) === 'application/json') {
            $request->setRequestFormat('json');
        }

        return $request;
    }

    private function addQueryParameters(Request $request, array $query): Request
    {
        $request->query->add($query);
        $request->server->add(['QUERY_STRING' => http_build_query($query)]);

        return $request;
    }

    private function addBodyParameters(Request $request, array $body): Request
    {
        $request->request->add($body);

        return $request;
    }

    /**
     * @return JsonResponse|mixed|\Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception
     */
    private function makeApiCall(Request $request): mixed
    {
        if (config('apidoc.router') == 'dingo') {
            $response = $this->callDingoRoute($request);
        } else {
            $response = $this->callLaravelRoute($request);
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    private function callLaravelRoute(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = app(Kernel::class);

        //Disable middlewares
        $without_middleware = $this->rulesToApply['without_middleware'];

        if (! empty($without_middleware)) {

            if (in_array('*', $without_middleware)) {

                $kernel->getApplication()->instance('middleware.disable', true);

            } else {

                foreach ((array) $without_middleware as $abstract) {
                    $kernel->getApplication()->instance($abstract, new class
                    {
                        public function handle($request, $next)
                        {
                            return $next($request);
                        }
                    });
                }

            }
        }

        $response = $kernel->handle($request);

        $kernel->terminate($request, $response);

        return $response;
    }

    private function shouldMakeApiCall(Route $route, array $rulesToApply): bool
    {
        $allowedMethods = $rulesToApply['methods'] ?? [];
        if (empty($allowedMethods)) {
            return false;
        }

        if (is_string($allowedMethods) && $allowedMethods == '*') {
            return true;
        }

        if (in_array('*', $allowedMethods)) {
            return true;
        }

        $routeMethods = $this->getMethods($route);
        if (in_array(array_shift($routeMethods), $allowedMethods)) {
            return true;
        }

        return false;
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        $prefix = 'HTTP_';
        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');
            if (! Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE') {
                $name = $prefix.$name;
            }
            $server[$name] = $value;
        }

        return $server;
    }
}
