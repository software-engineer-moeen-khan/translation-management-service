<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_the_given_password(): void
    {
        $this->artisan('user:create', ['email' => 'jane@example.com', '--name' => 'Jane', '--password' => 'correct-horse'])
            ->expectsOutputToContain('User jane@example.com created.')
            ->doesntExpectOutputToContain('Generated password')
            ->assertSuccessful();

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertSame('Jane', $user->name);
        $this->assertNotSame('correct-horse', $user->password);
        $this->assertTrue(Hash::check('correct-horse', $user->password));
    }

    public function test_it_generates_a_password_when_none_is_given(): void
    {
        $this->artisan('user:create', ['email' => 'jane@example.com'])
            ->expectsOutputToContain('Generated password: ')
            ->assertSuccessful();

        $this->assertSame('jane', User::query()->where('email', 'jane@example.com')->value('name'));
    }

    public function test_it_rejects_invalid_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('user:create', ['email' => 'not-an-email'])->assertExitCode(2);
        $this->artisan('user:create', ['email' => 'taken@example.com'])->assertExitCode(2);
        $this->artisan('user:create', ['email' => 'jane@example.com', '--password' => 'short'])->assertExitCode(2);

        $this->assertSame(1, User::query()->count());
    }
}
