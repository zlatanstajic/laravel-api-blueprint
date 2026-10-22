<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\User\UserInvalidCredentialsException;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        } catch (UserInvalidCredentialsException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->serverErrorResponse($e);
        }
    }
}
