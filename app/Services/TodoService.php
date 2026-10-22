<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Todo;
use App\Repositories\TodoRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * TodoService handles business logic related to todos.
 */
class TodoService
{
    public function __construct(public readonly TodoRepository $todoRepository)
    {
        //
    }

    /**
     * Get all todos.
     *
     * @return Collection<int, Todo>
     */
    public function getAllTodos()
    {
        return $this->todoRepository->getAll();
    }

    /**
     * Get todo by ID.
     */
    public function getTodoById(int $id): ?Todo
    {
        return $this->todoRepository->findById($id);
    }

    /**
     * Create a new todo.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTodo(array $data): Todo
    {
        return $this->todoRepository->create($data);
    }

    /**
     * Update a todo.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTodo(int $id, array $data): Todo
    {
        return $this->todoRepository->update($id, $data);
    }

    /**
     * Delete a todo.
     */
    public function deleteTodo(int $id): bool
    {
        return $this->todoRepository->delete($id);
    }
}
