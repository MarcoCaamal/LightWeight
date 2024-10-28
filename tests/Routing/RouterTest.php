<?php

namespace SMFramework\Tests\Routing;

use SMFramework\Http\Contracts\MiddlewareContract;
use SMFramework\Http\Response;
use PHPUnit\Framework\TestCase;

use SMFramework\Server\ServerContract;
use SMFramework\Http\HttpMethod;
use SMFramework\Http\Request;
use SMFramework\Routing\Router;

class RouterTest extends TestCase
{
    private function createMockRequest(string $uri, HttpMethod $httpMethod): Request
    {
        return (new Request())
            ->setUri($uri)
            ->setMethod($httpMethod);
    }

    public function testResolveBasicRouteWithCallback()
    {
        $uri = '/test';
        $action = fn () => 'test';
        $router = new Router();
        $router->get($uri, $action);
        $route = $router->resolveRoute($this->createMockRequest($uri, HttpMethod::GET));

        $this->assertEquals($action, $route->action());
        $this->assertEquals($uri, $route->uri());
    }

    public function testResolveMultipleBasicRoutesWithCallbackAction()
    {
        $routes = [
            '/test' => fn () => 'test',
            '/something' => fn () => 'something',
            'fizz' => fn () => 'fizz'
        ];

        $router = new Router();

        foreach ($routes as $uri => $action) {
            $router->get($uri, $action);
        }

        foreach ($routes as $uri => $action) {
            $route = $router->resolveRoute($this->createMockRequest($uri, HttpMethod::GET));
            $this->assertEquals($action, $route->action());
            $this->assertEquals($uri, $route->uri());
        }
    }

    public function testResolveMultipleBasicRoutesWithCallbackActionForDifferentHttpMethods()
    {
        $routes = [
            [HttpMethod::GET, "/test", fn () => "get"],
            [HttpMethod::POST, "/test", fn () => "post"],
            [HttpMethod::PUT, "/test", fn () => "put"],
            [HttpMethod::PATCH, "/test", fn () => "patch"],
            [HttpMethod::DELETE, "/test", fn () => "delete"],

            [HttpMethod::GET, '/random-route', fn () => 'get'],
            [HttpMethod::POST, '/other-random-route', fn () => 'post'],
            [HttpMethod::PUT, "/something", fn () => "put"],
            [HttpMethod::PATCH, "/other-router", fn () => "patch"],
            [HttpMethod::DELETE, "/d", fn () => "delete"],
        ];

        $router = new Router();

        foreach ($routes as [$method, $uri, $action]) {
            $router->{strtolower($method->value)}($uri, $action);
        }

        foreach ($routes as [$method, $uri, $action]) {
            $route = $router->resolveRoute($this->createMockRequest($uri, $method));
            $this->assertEquals($route->action(), $action);
            $this->assertEquals($route->uri(), $uri);
        }
    }
    public function testRunMiddlewares()
    {
        $middleware1 = new class () implements MiddlewareContract {
            /**
             *
             * @param Request $request
             * @param \Closure $next
             */
            public function handle(Request $request, \Closure $next)
            {
                $response = $next($request);
                $response->setHeader('x-test-one', 'test one');

                return $response;
            }
        };

        $middleware2 = new class () implements MiddlewareContract {
            /**
             *
             * @param Request $request
             * @param \Closure $next
             */
            public function handle(Request $request, \Closure $next)
            {
                $response = $next($request);
                $response->setHeader('x-test-two', 'test two');

                return $response;
            }
        };

        $router = new Router();
        $uri = '/test';
        $expectedResponse = Response::text('test');
        $router->get($uri, fn ($request) => $expectedResponse)
            ->setMiddlewares([$middleware1::class, $middleware2::class]);

        $response = $router->resolve($this->createMockRequest($uri, HttpMethod::GET));

        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals($response->headers('x-test-one'), 'test one');
        $this->assertEquals($response->headers('x-test-two'), 'test two');
    }
    public function testMiddlewareStackCanBeStopped()
    {
        $stopMiddleware = new class () implements MiddlewareContract {
            public function handle(Request $request, \Closure $next)
            {
                return Response::text('STOP!');
            }
        };

        $middleware2 = new class () implements MiddlewareContract {
            /**
             *
             * @param Request $request
             * @param \Closure $next
             */
            public function handle(Request $request, \Closure $next)
            {
                $response = $next($request);
                $response->setHeader('x-test-two', 'test two');

                return $response;
            }
        };

        $router = new Router();
        $uri = '/test';
        $unreachableResponse = Response::text('Unreacheable');
        $router->get($uri, fn ($request) => $unreachableResponse)
            ->setMiddlewares([$stopMiddleware, $middleware2]);

        $response = $router->resolve($this->createMockRequest($uri, HttpMethod::GET));

        $this->assertEquals('STOP!', $response->getContent());
        $this->assertNull($response->headers('x-test-two'));
    }
}
