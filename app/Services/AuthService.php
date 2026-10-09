<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    private const FALLBACK_HASH_KEY = 'auth:fallback-password-hash';

    public function __construct(
        private readonly Hasher $hasher,
        private readonly Cache $cache,
    ) {
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

    /**
     * Hash compared against when the email is unknown, so that a failed login
     * costs one password check whether or not the account exists. It is created
     * once and cached; hashing on every attempt would itself give the answer away.
     */
    private function fallbackHash(): string
    {
        return $this->cache->rememberForever(
            self::FALLBACK_HASH_KEY,
            fn (): string => $this->hasher->make(Str::random(40)),
        );
    }
}
