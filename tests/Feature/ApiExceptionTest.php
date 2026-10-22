<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TodoService;
use App\Services\TokenService;
use Exception;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use TypeError;

/**
 * Unexpected failures must never disclose their messages or determine HTTP status.
 */
class ApiExceptionTest extends TestCase
{
    /**
     * Every controller operation, including both update methods.
     *
     * @return array<string, array{string, string, class-string, string}>
     */
    public static function operations(): array
    {
        return [
            'list' => ['GET', '/api/todos', TodoService::class, 'getAllTodos'],
            'create' => ['POST', '/api/todos', TodoService::class, 'createTodo'],
            'read' => ['GET', '/api/todos/1', TodoService::class, 'getTodoById'],
            'replace' => ['PUT', '/api/todos/1', TodoService::class, 'updateTodo'],
            'update' => ['PATCH', '/api/todos/1', TodoService::class, 'updateTodo'],
            'delete' => ['DELETE', '/api/todos/1', TodoService::class, 'deleteTodo'],
            'token' => ['POST', '/api/tokens', TokenService::class, 'authenticate'],
        ];
    }

    /**
     * Generic exceptions, including valid-looking codes, stay private.
     */
    #[DataProvider('operations')]
    public function test_unexpected_exceptions_return_generic_server_errors(
        string $method, string $uri, string $service, string $operation
    ): void {
        $this->withoutMiddleware(ThrottleRequests::class);
        Sanctum::actingAs(new User(['id' => 1]));
        $mock = Mockery::mock($service);
        $this->app->instance($service, $mock);

        foreach ([0, 422, 999] as $code) {
            $exception = new Exception('Private database details', $code);
            $mock->shouldReceive($operation)->once()->andThrow($exception);
            Log::shouldReceive('error')->once()->with('Unexpected API failure.', [
                'exception_type' => Exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            $this->json($method, $uri, ['title' => 'Test', 'email' => 'test@example.com', 'password' => 'wrong-password'])
                ->assertInternalServerError()
                ->assertExactJson(['error' => 'An unexpected error occurred.']);
        }
    }

    /**
     * Type errors follow the same generic response and Serbian translation.
     */
    public function test_type_errors_are_hidden_and_localized(): void
    {
        config()->set('app.locale', 'sr');
        $this->app->setLocale('sr');
        Sanctum::actingAs(new User);
        $mock = Mockery::mock(TodoService::class);
        $exception = new TypeError('Private type details');
        $mock->shouldReceive('getAllTodos')->once()->andThrow($exception);
        Log::shouldReceive('error')->once()->with('Unexpected API failure.', [
            'exception_type' => TypeError::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
        $this->app->instance(TodoService::class, $mock);

        $this->getJson('/api/todos')
            ->assertInternalServerError()
            ->assertExactJson(['error' => 'Došlo je do neočekivane greške.']);
    }
}
