<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_exchange_credentials_for_a_token(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
            'device_name' => 'ci',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at'])
            ->assertJsonPath('token_type', 'Bearer');

        $token = PersonalAccessToken::findToken($response->json('token'));

        $this->assertNotNull($token);
        $this->assertTrue($token->tokenable->is($user));
        $this->assertSame('ci', $token->name);
        $this->assertNotNull($token->expires_at);
    }

    public function test_token_does_not_expire_when_expiration_is_disabled(): void
    {
        config(['sanctum.expiration' => null]);
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('expires_at', null);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_fails_identically_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid credentials.']);
    }

    public function test_unknown_emails_still_cost_exactly_one_password_check(): void
    {
        try {
            $this->app->make(AuthService::class)->issueToken('ghost@example.com', 'password', 'ci');
            $this->fail('Unknown credentials were accepted.');
        } catch (AuthenticationException) {
            // Expected; this first attempt created the placeholder hash.
        }

        // The placeholder hash is created once and reused, so later attempts do the
        // same work as an attempt against a real account: a single check.
        $this->mock(Hasher::class, function (MockInterface $hasher): void {
            $hasher->shouldReceive('make')->never();
            $hasher->shouldReceive('check')->once()->andReturnFalse();
        });

        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'password'])
            ->assertUnauthorized();
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        config(['translations.rate_limits.login' => 2]);
        $payload = ['email' => 'jane@example.com', 'password' => 'nope'];

        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', $payload)->assertTooManyRequests();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ci')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_protected_endpoints_reject_missing_and_invalid_tokens(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->withToken('not-a-real-token')->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ci', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $this->post('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_responses_carry_security_headers(): void
    {
        $responses = [
            'validation error' => $this->postJson('/api/v1/auth/login', []),
            'unauthenticated' => $this->getJson('/api/v1/translations'),
            'not found' => $this->getJson('/api/v1/nope'),
        ];

        foreach ($responses as $response) {
            $response
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'no-referrer');
        }
    }
}
