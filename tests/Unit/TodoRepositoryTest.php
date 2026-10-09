<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Todo\TodoNotFoundException;
use App\Models\Todo as BaseTodo;
use App\Repositories\TodoRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;

class TodoStub extends BaseTodo
{
    public static $allReturn = null;

    public static $findOrFailReturn = null;

    public static $findOrFailThrow = false;

    public static $createReturn = null;

    public static $destroyReturn = 0;

    public static function all($columns = ['*'])
    {
        return self::$allReturn;
    }

    public static function findOrFail($id, $columns = ['*'])
    {
        throw_if(self::$findOrFailThrow, ModelNotFoundException::class);

        return self::$findOrFailReturn;
    }

    public static function create(array $data = [])
    {
        return self::$createReturn ?? new BaseTodo;
    }

    public static function destroy($ids)
    {
        return self::$destroyReturn;
    }

    public function update(array $attributes = [], array $options = [])
    {
        return true;
    }
}

class TodoRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        TodoStub::$allReturn = null;
        TodoStub::$findOrFailReturn = null;
        TodoStub::$findOrFailThrow = false;
        TodoStub::$createReturn = null;
        TodoStub::$destroyReturn = 0;
    }

    public function test_get_all_returns_collection(): void
    {
        $coll = new Collection(['a']);

        TodoStub::$allReturn = $coll;

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertSame($coll, $repo->getAll());
    }

    public function test_find_by_id_returns_todo_when_found(): void
    {
        $model = new BaseTodo;

        TodoStub::$findOrFailReturn = $model;

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertSame($model, $repo->findById(5));
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        TodoStub::$findOrFailThrow = true;

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains((string) $msg, '12'));

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertNull($repo->findById(12));
    }

    public function test_create_returns_todo(): void
    {
        $model = new BaseTodo;

        TodoStub::$createReturn = $model;

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertSame($model, $repo->create(['x' => 'y']));
    }

    public function test_update_updates_and_returns_model(): void
    {
        $instance = new class extends BaseTodo
        {
            public function update(array $attributes = [],
                array $options = [])
            {
                return true;
            }
        };

        TodoStub::$findOrFailReturn = $instance;

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertSame($instance, $repo->update(7, ['t' => 'v']));
    }

    public function test_update_throws_when_not_found(): void
    {
        $this->expectException(TodoNotFoundException::class);

        TodoStub::$findOrFailThrow = true;

        $c = new Container;
        Container::setInstance($c);
        $c->singleton('translator', fn () => new class
        {
            public function get($key, $replace = [], $locale = null)
            {
                return 'not found';
            }
        });

        Log::shouldReceive('warning')
            ->once()
            ->with('Todo item ID 3 not found.');

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $repo->update(3, ['a' => 'b']);
    }

    public function test_delete_returns_boolean(): void
    {
        TodoStub::$destroyReturn = 1;

        $repo = new class extends TodoRepository
        {
            public function __construct()
            {
                $this->model = TodoStub::class;
            }
        };

        $this->assertTrue($repo->delete(4));

        TodoStub::$destroyReturn = 0;

        $this->assertFalse($repo->delete(5));
    }
}
