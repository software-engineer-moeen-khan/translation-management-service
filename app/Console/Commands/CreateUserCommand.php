<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
        {email : Email address used to log in}
        {--name= : Display name (defaults to the part before the @)}
        {--password= : Password (a random one is generated and shown when omitted)}';

    protected $description = 'Create a user that can obtain API tokens';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = $this->option('password') ?? Str::password(20);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::INVALID;
        }

        User::query()->create([
            'name' => $this->option('name') ?? Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("User {$email} created.");

        if ($this->option('password') === null) {
            $this->line("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
