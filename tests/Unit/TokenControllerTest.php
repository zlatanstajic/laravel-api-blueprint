<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\TokenController;
use App\Services\TokenService;
use Closure;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TokenControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $c = new Container;
        Container::setInstance($c);

        $c->singleton(fn (): ResponseFactory => new class implements ResponseFactory
        {
            public function make($content = '', $status = 200,
                array $headers = [])
            {
                return new JsonResponse($content, $status);
            }

            public function noContent($status = 204,
                array $headers = [])
            {
                return new JsonResponse([], $status);
            }

            public function view($view, $data = [], $status = 200,
                array $headers = [])
            {
                return new JsonResponse($data, $status);
            }

            public function json($data = [], $status = 200,
                array $headers = [], $options = 0)
            {
                return new JsonResponse($data, $status);
            }

            public function jsonp($callback, $data = [], $status = 200,
                array $headers = [], $options = 0)
            {
                return new JsonResponse([$callback, $data], $status);
            }

            public function eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>'): StreamedResponse
            {
                return new StreamedResponse($callback, 200, $headers);
            }

            public function stream($callback, $status = 200,
                array $headers = [])
            {
                return new JsonResponse([], $status);
            }

            public function streamJson($data, $status = 200,
                $headers = [], $encodingOptions = 15)
            {
                return new JsonResponse($data, $status);
            }

            public function streamDownload($callback, $name = null,
                array $headers = [], $disposition = 'attachment')
            {
                return new JsonResponse([], 200);
            }

            public function download($file, $name = null,
                array $headers = [], $disposition = 'attachment')
            {
                return new JsonResponse([], 200);
            }

            public function file($file, array $headers = [])
            {
                return new JsonResponse([], 200);
            }

            public function redirectTo($path, $status = 302,
                $headers = [], $secure = null)
            {
                return new JsonResponse(['redirect' => $path],
                    $status);
            }

            public function redirectToRoute($route, $parameters = [],
                $status = 302, $headers = [])
            {
                return new JsonResponse(['route' => $route], $status);
            }

            public function redirectToAction($action,
                $parameters = [], $status = 302, $headers = [])
            {
                return new JsonResponse(['action' => $action], $status);
            }

            public function redirectGuest($path, $status = 302,
                $headers = [], $secure = null)
            {
                return new JsonResponse(['guest' => $path], $status);
            }

            public function redirectToIntended($default = '/',
                $status = 302, $headers = [], $secure = null)
            {
                return new JsonResponse(['intended' => $default],
                    $status);
            }
        });

        $c->singleton('translator', fn () => new class
        {
            public function get($k)
            {
                return 'ok';
            }
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_create_returns_token_on_success(): void
    {
        $data = [
            'email' => 'a@b.com',
            'password' => 'secret',
        ];

        $req = Mockery::mock(Request::class);
        $req->shouldReceive('validate')->once()->andReturn($data);

        $service = Mockery::mock(TokenService::class);
        $service->shouldReceive('authenticate')
            ->once()
            ->with($data['email'], $data['password'])
            ->andReturn('tok123');

        $ctrl = new TokenController($service);

        $resp = $ctrl->create($req);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(200, $resp->getStatusCode());

        $this->assertSame(['data' => ['token' => 'tok123']],
            $resp->getData(true));
    }

    public function test_create_handles_authenticate_exception(): void
    {
        $data = [
            'email' => 'x@x.com',
            'password' => 'pw',
        ];

        $req = Mockery::mock(Request::class);
        $req->shouldReceive('validate')->once()->andReturn($data);

        $service = Mockery::mock(TokenService::class);
        $service->shouldReceive('authenticate')
            ->once()
            ->andThrow(new Exception('bad creds', 401));

        $ctrl = new TokenController($service);

        $resp = $ctrl->create($req);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(401, $resp->getStatusCode());
        $this->assertSame(['error' => 'bad creds'], $resp->getData(true));
    }
}
