<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The development schema viewer must stay private outside local development.
 */
class SchemaViewerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Non-local requests cannot access either the viewer or its schema endpoint.
     */
    public function test_viewer_is_disabled_outside_local(): void
    {
        $this->assertFalse(config('truss.enabled'));
        $this->assertFalse(config('truss.mcp.enabled'));
        $this->assertFalse(config('truss.diff.enabled'));
        $this->get('/truss')->assertNotFound();
        $this->getJson('/truss/api/schema')->assertNotFound();
    }

    /**
     * The local viewer renders and exposes schema structure from the test database.
     */
    public function test_local_viewer_serves_the_database_structure(): void
    {
        $this->app->instance('env', 'local');
        config()->set('truss.enabled', true);

        User::factory()->create(['name' => 'Private row marker', 'email' => 'private-row@example.com']);
        $this->get('/truss')->assertOk();
        $response = $this->getJson('/truss/api/schema')->assertOk();
        $this->assertStringContainsString('todos', $response->getContent());
        $this->assertStringContainsString('user_id', $response->getContent());
        $this->assertStringNotContainsString('Private row marker', $response->getContent());
        $this->assertStringNotContainsString('private-row@example.com', $response->getContent());
    }

    /**
     * Keep schema inspection and migrations on a disposable database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.url', null);
    }
}
