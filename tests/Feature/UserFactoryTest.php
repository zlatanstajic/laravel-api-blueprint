<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verify the seeded admin account follows the application configuration.
 */
class UserFactoryTest extends TestCase
{
    /**
     * The admin state reads its credentials from configuration.
     */
    public function test_admin_state_uses_configured_credentials(): void
    {
        config()->set('app.admin', [
            'name' => 'Configured Admin',
            'email' => 'configured-admin@example.com',
            'password' => 'configured-password',
        ]);

        $user = User::factory()->admin()->make();

        $this->assertSame('Configured Admin', $user->name);
        $this->assertSame('configured-admin@example.com', $user->email);
        $this->assertTrue(Hash::check('configured-password', $user->password));
    }
}
