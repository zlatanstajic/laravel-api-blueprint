<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Todo\TodoDeleteFailedException;
use App\Exceptions\Todo\TodoNotFoundException;
use App\Http\Resources\TodoResource;
use App\Services\TodoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Todo Controller
 */
class TodoController extends Controller
{
    public function __construct(public readonly TodoService $todoService)
    {
        //
    }

    /**
     * Get all todos.
     */
    public function index(): JsonResponse
    {
        try {
            return $this->successResponse(
                TodoResource::collection(
                    $this->todoService->getAllTodos()
                )
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get a specific todo by ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $todo = $this->todoService->getTodoById($id);

            throw_unless($todo, TodoNotFoundException::class);

            return $this->successResponse(new TodoResource($todo));
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Create a new todo.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'completed' => ['boolean'],
        ]);

        $data['user_id'] = $request->user()->id;

        try {
            return $this->successResponse(
                data: new TodoResource($this->todoService->createTodo($data)),
                code: Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update an existing todo.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'completed' => ['boolean'],
        ]);

        try {
            return $this->successResponse(
                new TodoResource($this->todoService->updateTodo($id, $data))
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Delete a todo.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            throw_unless($this->todoService->deleteTodo($id), TodoDeleteFailedException::class);

            return $this->successResponse();
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }
}
