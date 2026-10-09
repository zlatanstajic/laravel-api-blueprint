<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TokenService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token Controller
 */
class TokenController extends Controller
{
    public function __construct(public readonly TokenService $tokenService)
    {
        //
    }

    /**
     * Authenticate user.
     */
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:5'],
        ]);

        try {
            return $this->successResponse([
                'token' => $this->tokenService->authenticate(
                    $data['email'],
                    $data['password'],
                ),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }
}
