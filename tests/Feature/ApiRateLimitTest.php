<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Exercise the real API and token throttles together.
 */
class ApiRateLimitTest extends TestCase
{
    /**
     * Keep limiter state in a fresh local array cache for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();
    }

    /**
     * Three token requests are allowed and only the fourth is blocked.
     */
    public function test_token_limit_allows_three_requests_and_recovers(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/tokens')->assertUnprocessable();
        }

        $this->postJson('/api/tokens')->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');
        $this->getJson('/api')->assertOk();

        $this->travel(61)->seconds();
        $this->postJson('/api/tokens')->assertUnprocessable();
    }

    /**
     * Ordinary API requests must not consume the separate token allowance.
     */
    public function test_api_requests_do_not_consume_token_allowance(): void
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->getJson('/api')->assertOk();
        }

        $this->postJson('/api/tokens')->assertUnprocessable();
    }

    /**
     * Token attempts still count exactly once against the global API allowance.
     */
    public function test_token_requests_also_count_toward_global_limit(): void
    {
        $this->postJson('/api/tokens')->assertUnprocessable();

        for ($attempt = 0; $attempt < 59; $attempt++) {
            $this->getJson('/api')->assertOk();
        }

        $this->getJson('/api')->assertTooManyRequests()->assertHeader('X-RateLimit-Limit', '60');
        $this->postJson('/api/tokens')->assertTooManyRequests()->assertHeader('X-RateLimit-Limit', '60');
    }

    /**
     * Another client retains its own token allowance.
     */
    public function test_token_allowance_is_separate_for_each_ip(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/tokens')->assertUnprocessable();
        }

        $this->postJson('/api/tokens')->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/tokens')->assertUnprocessable();
    }
}
