<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verify that missing API authentication renders as a JSON 401 response.
 */
class ApiAuthenticationTest extends TestCase
{
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
