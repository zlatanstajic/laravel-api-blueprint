<?php

declare(strict_types=1);

use App\Http\Controllers\TodoController;
use App\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1,api:')->group(function (): void {
    /**
     * Public routes
     */
    Route::group(['prefix' => '/'], function (): void {
        /**
         * Authentication
         */
        Route::middleware('throttle:3,1,tokens:')
            ->post('/tokens', [TokenController::class, 'create'])
            ->name('tokens.create');

        /**
         * API Home Route
         */
        Route::get('/', fn () => [
            'data' => [
                'message' => __('messages.default.welcome'),
            ],
        ])->name('home');
    });

    /**
     * Protected routes
     */
    Route::group(['middleware' => 'auth:sanctum'], function (): void {
        /**
         * Todos
         */
        Route::apiResource('todos', TodoController::class)->names([
            'index' => 'todos.index',
            'store' => 'todos.store',
            'show' => 'todos.show',
            'update' => 'todos.update',
            'destroy' => 'todos.destroy',
        ]);
    });
});
