<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\TodoController;
use App\Models\Todo;
use App\Services\TodoService;
use Closure;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TypeError;

class TodoControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $c = new Container;
        Container::setInstance($c);

        $c->singleton(fn (): ResponseFactory => new class implements ResponseFactory
        {
            public function make($content = '', $status = 200, array $headers = [])
            {
                $content = $this->normalize($content);
                $status = $this->normalizeStatus($status);

                return new JsonResponse($content, $status);
            }

            public function noContent($status = 204, array $headers = [])
            {
                return new JsonResponse([], $status);
            }

            public function view($view, $data = [], $status = 200, array $headers = [])
            {
                return new JsonResponse($data, $status);
            }

            public function json($data = [], $status = 200, array $headers = [], $options = 0)
            {
                $data = $this->normalize($data);
                $status = $this->normalizeStatus($status);

                return new JsonResponse($data, $status);
            }

            public function jsonp($callback, $data = [], $status = 200, array $headers = [], $options = 0)
            {
                return new JsonResponse([$callback, $data], $status);
            }

            public function eventStream(Closure $callback, array $headers = [], StreamedEvent|string|null $endStreamWith = '</stream>'): StreamedResponse
            {
                return new StreamedResponse($callback, 200, $headers);
            }

            public function stream($callback, $status = 200, array $headers = [])
            {
                return new JsonResponse([], $status);
            }

            public function streamJson($data, $status = 200, $headers = [], $encodingOptions = 15)
            {
                return new JsonResponse($data, $status);
            }

            public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
            {
                return new JsonResponse([], 200);
            }

            public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
            {
                return new JsonResponse([], 200);
            }

            public function file($file, array $headers = [])
            {
                return new JsonResponse([], 200);
            }

            public function redirectTo($path, $status = 302, $headers = [], $secure = null)
            {
                return new JsonResponse(['redirect' => $path], $status);
            }

            public function redirectToRoute($route, $parameters = [], $status = 302, $headers = [])
            {
                return new JsonResponse(['route' => $route], $status);
            }

            public function redirectToAction($action, $parameters = [], $status = 302, $headers = [])
            {
                return new JsonResponse(['action' => $action], $status);
            }

            public function redirectGuest($path, $status = 302, $headers = [], $secure = null)
            {
                return new JsonResponse(['guest' => $path], $status);
            }

            public function redirectToIntended($default = '/', $status = 302, $headers = [], $secure = null)
            {
                return new JsonResponse(['intended' => $default], $status);
            }

            private function normalize($value)
            {
                if (is_array($value)) {
                    $out = [];
                    foreach ($value as $k => $v) {
                        $out[$k] = $this->normalize($v);
                    }

                    return $out;
                }

                if (is_object($value) && method_exists($value, 'toArray')) {
                    try {
                        $rm = new ReflectionMethod($value, 'toArray');
                        $params = $rm->getParameters();

                        if (count($params) === 0) {
                            return $value->toArray();
                        }

                        $p = $params[0];

                        if (! $p->hasType() || $p->allowsNull()) {
                            return $value->toArray(null);
                        }

                        // Provide a minimal Request instance if param is typed
                        return $value->toArray(new Request);
                    } catch (ReflectionException|TypeError) {
                        return $value->toArray(null);
                    }
                }

                return $value;
            }

            private function normalizeStatus($status)
            {
                if (! is_int($status) || $status < 100 || $status > 599) {
                    return 500;
                }

                return $status;
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

    public function test_index_returns_success_with_collection(): void
    {
        $item = new Todo;
        $item->id = 1;

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('getAllTodos')
            ->once()
            ->andReturn([$item]);

        $controller = new TodoController($service);

        $resp = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $resp);

        $body = $resp->getData(true);

        $this->assertIsArray($body);
        $this->assertNotEmpty($body);
    }

    public function test_show_returns_not_found_when_missing(): void
    {
        $c = Container::getInstance();
        $c->singleton('translator', fn () => new class
        {
            public function get($k)
            {
                return 'not found';
            }
        });

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('getTodoById')->once()->with(5)
            ->andReturnNull();

        $controller = new TodoController($service);

        $resp = $controller->show(5);

        $this->assertInstanceOf(JsonResponse::class, $resp);

        $this->assertSame(404, $resp->getStatusCode());
    }

    public function test_show_returns_success_when_found(): void
    {
        $todo = new Todo;
        $todo->id = 4;

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('getTodoById')
            ->once()
            ->with(4)
            ->andReturn($todo);

        $controller = new TodoController($service);

        $resp = $controller->show(4);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(200, $resp->getStatusCode());

        $body = $resp->getData(true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
    }

    public function test_store_validates_and_creates_todo(): void
    {
        $data = ['title' => 'x'];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()
            ->andReturn($data);

        $user = (object) ['id' => 7];
        $request->shouldReceive('user')->once()->andReturn($user);

        $created = new Todo;
        $created->id = 2;

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('createTodo')
            ->once()
            ->withArgs(fn ($arg) => isset($arg['user_id']))
            ->andReturn($created);

        $controller = new TodoController($service);

        $resp = $controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(201, $resp->getStatusCode(), print_r($resp->getData(true), true));
    }

    public function test_update_calls_service_and_returns_resource(): void
    {
        $data = ['title' => 'y'];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()
            ->andReturn($data);

        $updated = new Todo;
        $updated->id = 3;

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('updateTodo')
            ->once()
            ->with(3, $data)
            ->andReturn($updated);

        $controller = new TodoController($service);

        $resp = $controller->update($request, 3);

        $this->assertInstanceOf(JsonResponse::class, $resp);
    }

    public function test_destroy_throws_when_delete_fails(): void
    {
        $c = Container::getInstance();
        $c->singleton('translator', fn () => new class
        {
            public function get($k)
            {
                return 'failed';
            }
        });

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('deleteTodo')->once()->with(9)
            ->andReturnFalse();

        $controller = new TodoController($service);

        $resp = $controller->destroy(9);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(409, $resp->getStatusCode());
    }

    public function test_destroy_returns_success_when_deleted(): void
    {
        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('deleteTodo')->once()->with(8)
            ->andReturnTrue();

        $controller = new TodoController($service);

        $resp = $controller->destroy(8);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(200, $resp->getStatusCode(), print_r($resp->getData(true), true));
    }

    public function test_index_handles_service_exception(): void
    {
        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('getAllTodos')
            ->once()
            ->andThrow(new Exception('index fail'));

        $controller = new TodoController($service);

        $resp = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(500, $resp->getStatusCode());
        $this->assertSame(['error' => 'index fail'], $resp->getData(true));
    }

    public function test_store_handles_service_exception(): void
    {
        $data = ['title' => 'x'];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()->andReturn($data);
        $request->shouldReceive('user')->once()->andReturn((object) ['id' => 1]);

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('createTodo')
            ->once()
            ->andThrow(new Exception('store fail'));

        $controller = new TodoController($service);

        $resp = $controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(500, $resp->getStatusCode());
        $this->assertSame(['error' => 'store fail'], $resp->getData(true));
    }

    public function test_update_handles_service_exception(): void
    {
        $data = ['title' => 'y'];

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()->andReturn($data);

        $service = Mockery::mock(TodoService::class);
        $service->shouldReceive('updateTodo')
            ->once()
            ->andThrow(new Exception('update fail', 422));

        $controller = new TodoController($service);

        $resp = $controller->update($request, 5);

        $this->assertInstanceOf(JsonResponse::class, $resp);
        $this->assertSame(422, $resp->getStatusCode());
        $this->assertSame(['error' => 'update fail'], $resp->getData(true));
    }
}
