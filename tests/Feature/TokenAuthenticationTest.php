<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Login failures must not reveal whether an email address exists.
 */
class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Invalid token states tested through Sanctum rather than a simulated user.
     *
     * @return array<string, array{string}>
     */
    public static function rejectedTokenStates(): array
    {
        return [
            'expired by age' => ['age'],
            'expired explicitly' => ['expiry'],
            'revoked' => ['revoked'],
            'deleted user' => ['deleted'],
            'invalid secret' => ['secret'],
            'malformed' => ['malformed'],
        ];
    }

    /**
     * Password shapes must not reveal whether a user exists.
     *
     * @return array<string, array{string}>
     */
    public static function invalidPasswords(): array
    {
        return [
            'ordinary' => ['incorrect-password'],
            'long' => [str_repeat('x', 100)],
            'null byte' => ["wrong\0password"],
        ];
    }

    /**
     * Unusable bearer tokens cannot access protected data.
     */
    #[DataProvider('rejectedTokenStates')]
    public function test_unusable_tokens_are_rejected(string $state): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Test');
        $bearer = $token->plainTextToken;

        switch ($state) {
            case 'age':
                $token->accessToken->forceFill(['created_at' => now()->subDay()->subSecond()])->save();
                break;
            case 'expiry':
                $token->accessToken->forceFill(['expires_at' => now()->subSecond()])->save();
                break;
            case 'revoked':
                $token->accessToken->delete();
                break;
            case 'deleted':
                $user->delete();
                break;
            case 'secret':
                $bearer = $token->accessToken->id.'|incorrect-secret';
                break;
            case 'malformed':
                $bearer = 'invalid|token';
                break;
        }

        $this->withToken($bearer)->getJson('/api/todos')
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Unknown email and incorrect password produce the same status and body.
     */
    #[DataProvider('invalidPasswords')]
    public function test_invalid_credentials_have_identical_responses(string $password): void
    {
        Log::shouldReceive('error')->never();
        $user = User::factory()->create();

        $unknown = $this->postJson('/api/tokens', [
            'email' => 'unknown@example.com', 'password' => $password,
        ])->assertUnauthorized()->assertExactJson(['error' => 'Invalid credentials']);

        $incorrect = $this->postJson('/api/tokens', [
            'email' => $user->email, 'password' => $password,
        ])->assertUnauthorized()->assertExactJson(['error' => 'Invalid credentials']);

        $this->assertSame($unknown->json(), $incorrect->json());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Correct credentials still issue a usable token.
     */
    public function test_valid_credentials_issue_a_usable_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/tokens', [
            'email' => $user->email, 'password' => 'password',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->withToken($response->json('data.token'))->getJson('/api/todos')
            ->assertOk()->assertExactJson(['data' => []]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /**
     * Keep test migrations away from the configured application database.
     */
    protected function beforeRefreshingDatabase(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('database.connections.sqlite.url', null);
    }
}
