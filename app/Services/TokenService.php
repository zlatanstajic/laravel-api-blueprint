<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\User\UserInvalidCredentialsException;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

/**
 * TokenService handles user authentication logic.
 */
class TokenService
{
    public function __construct(public readonly UserRepository $userRepository)
    {
        //
    }

    /**
     * Authenticate user.
     *
     * @throws UserInvalidCredentialsException
     */
    public function authenticate(string $email, string $password): string
    {
        $user = $this->userRepository->findByEmail($email);

        throw_unless($this->verifyPassword($user, $password), UserInvalidCredentialsException::class);

        return $user->createToken('API Token')->plainTextToken;
    }

    /**
     * Verify user password.
     */
    private function verifyPassword(?User $user, string $password): bool
    {
        if (! $user) {
            // Match password verification work without keeping a dummy credential.
            Hash::make('Authentication timing padding');

            return false;
        }

        return Hash::check($password, $user->password);
    }
}
