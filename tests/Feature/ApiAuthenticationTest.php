<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verify that missing API authentication renders as a JSON 401 response.
 */
class ApiAuthenticationTest extends TestCase
{
    /**
     * All methods exposed by the todo resource routes.
     *
     * @return array<string, array{string, string}>
     */
    public static function protectedRequests(): array
    {
        return [
            'list' => ['GET', '/api/todos'],
            'create' => ['POST', '/api/todos'],
            'read' => ['GET', '/api/todos/1'],
            'replace' => ['PUT', '/api/todos/1'],
            'update' => ['PATCH', '/api/todos/1'],
            'delete' => ['DELETE', '/api/todos/1'],
        ];
    }

    /**
     * Every todo operation rejects a missing token before reaching persistence.
     */
    #[DataProvider('protectedRequests')]
    public function test_all_todo_operations_require_authentication(string $method, string $uri): void
    {
        $this->call($method, $uri)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * A request without a token gets a JSON 401 even without a JSON Accept header.
     */
    public function test_unauthenticated_request_renders_json_without_accept_header(): void
    {
        $this->get(route('todos.index'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * A request without a token gets a JSON 401 when it asks for JSON.
     */
    public function test_unauthenticated_json_request_renders_json(): void
    {
        $this->getJson(route('todos.index'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
