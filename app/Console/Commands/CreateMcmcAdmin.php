<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateMcmcAdmin extends Command
{
    protected $signature = 'mcmc:create-admin
                            {--name= : Full name of the admin}
                            {--email= : Email address used to sign in}';

    protected $description = 'Create an MCMC staff account (for the first admin on a fresh server, without running the seeder)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');

        // The password is only ever asked interactively so it never lands in shell history.
        $password = $this->secret('Password (min 12 characters, mixed case and numbers)');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'username' => User::generateUsername($email === '' ? $name : strstr($email, '@', true)),
            'password' => $password,
            'role' => UserRole::McmcStaff,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("MCMC admin created. Sign in with {$user->email} or the username {$user->username}.");

        return self::SUCCESS;
    }
}
