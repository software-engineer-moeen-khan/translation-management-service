<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * Hash compared against when the email is unknown, so that a failed login
     * costs the same whether or not the account exists.
     */
    private ?string $fallbackHash = null;

    public function __construct(private readonly Hasher $hasher)
    {
    }

    /**
     * Exchange user credentials for a personal access token.
     *
     * @throws AuthenticationException
     */
    public function issueToken(string $email, string $password, string $deviceName): NewAccessToken
    {
        $user = User::query()->where('email', $email)->first();

        $passwordMatches = $this->hasher->check($password, $user?->password ?? $this->fallbackHash());

        if ($user === null || ! $passwordMatches) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $lifetime = config('sanctum.expiration');

        return $user->createToken(
            name: $deviceName,
            expiresAt: $lifetime ? now()->addMinutes((int) $lifetime) : null,
        );
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    private function fallbackHash(): string
    {
        return $this->fallbackHash ??= $this->hasher->make('fallback-password');
    }
}
