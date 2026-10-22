<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verify todo ownership through real bearer authentication and database queries.
 */
class TodoOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Resource operations and their existing rejection statuses.
     *
     * @return array<string, array{string, int}>
     */
    public static function foreignTodoRequests(): array
    {
        return [
            'read' => ['GET', 404],
            'replace' => ['PUT', 404],
            'update' => ['PATCH', 404],
            'delete' => ['DELETE', 409],
        ];
    }

    /**
     * A real bearer token lists only the owner's records.
     */
    public function test_list_excludes_another_users_todos(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $todo = Todo::factory()->create(['user_id' => $owner->id]);
        Todo::factory()->create(['user_id' => $other->id]);

        $this->withToken($owner->createToken('Test')->plainTextToken)
            ->getJson(route('todos.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $todo->id);
    }

    /**
     * Reading, updating, and deleting another user's todo leave it intact.
     */
    #[DataProvider('foreignTodoRequests')]
    public function test_another_users_todo_cannot_be_accessed(string $method, int $status): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $todo = Todo::factory()->create(['user_id' => $other->id, 'title' => 'Private todo']);

        $this->withToken($owner->createToken('Test')->plainTextToken)
            ->json($method, '/api/todos/'.$todo->id, ['title' => 'Changed'])
            ->assertStatus($status);

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'user_id' => $other->id,
            'title' => 'Private todo',
            'deleted_at' => null,
        ]);
    }

    /**
     * Creation takes ownership from the token even when the body supplies an owner.
     */
    public function test_creation_ignores_client_supplied_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->withToken($owner->createToken('Test')->plainTextToken)
            ->postJson(route('todos.store'), ['title' => 'My todo', 'user_id' => $other->id])
            ->assertCreated()
            ->assertJsonPath('data.attributes.user_id', $owner->id);

        $this->assertDatabaseHas('todos', ['title' => 'My todo', 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('todos', ['title' => 'My todo', 'user_id' => $other->id]);
    }

    /**
     * Updating a todo cannot transfer its ownership through request input.
     */
    public function test_update_ignores_client_supplied_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $todo = Todo::factory()->create(['user_id' => $owner->id]);

        $this->withToken($owner->createToken('Test')->plainTextToken)
            ->patchJson(route('todos.update', $todo->id), ['title' => 'Changed', 'user_id' => $other->id])
            ->assertOk()
            ->assertJsonPath('data.attributes.user_id', $owner->id);

        $this->assertDatabaseHas('todos', ['id' => $todo->id, 'title' => 'Changed', 'user_id' => $owner->id]);
    }

    /**
     * Ownership restrictions still allow the owner to read and soft delete a todo.
     */
    public function test_owner_can_read_and_delete_their_todo(): void
    {
        $owner = User::factory()->create();
        $todo = Todo::factory()->create(['user_id' => $owner->id]);
        $this->withToken($owner->createToken('Test')->plainTextToken);

        $this->getJson(route('todos.show', $todo->id))
            ->assertOk()
            ->assertJsonPath('data.id', $todo->id);
        $this->deleteJson(route('todos.destroy', $todo->id))->assertOk();

        $this->assertSoftDeleted('todos', ['id' => $todo->id]);
    }

    /**
     * Keep migrations and transactions on a disposable in-memory database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.url', null);
    }
}
