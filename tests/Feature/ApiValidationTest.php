<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify that invalid API input renders as a JSON 422 response.
 */
class ApiValidationTest extends TestCase
{
    /**
     * Token creation rejects invalid input with a 422.
     */
    public function test_token_creation_rejects_invalid_input(): void
    {
        $this->postJson(route('tokens.create'), ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Todo creation rejects a missing title with a 422.
     */
    public function test_todo_creation_rejects_missing_title(): void
    {
        Sanctum::actingAs(new User);

        $this->postJson(route('todos.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Todo update rejects invalid input with a 422.
     */
    public function test_todo_update_rejects_invalid_input(): void
    {
        Sanctum::actingAs(new User);

        $this->patchJson(route('todos.update', ['todo' => 1]), ['completed' => 'not-a-bool'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['completed']);
    }

    /**
     * Validation errors render as JSON when the client sends no JSON Accept header.
     */
    public function test_validation_errors_render_as_json_without_accept_header(): void
    {
        $this->post(route('tokens.create'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
