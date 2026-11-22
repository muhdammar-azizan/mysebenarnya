<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateMcmcAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_mcmc_staff_account(): void
    {
        $this->artisan('mcmc:create-admin', ['--name' => 'First Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (min 12 characters, mixed case and numbers)', 'StrongPass1234')
            ->expectsQuestion('Confirm password', 'StrongPass1234')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame(UserRole::McmcStaff, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('StrongPass1234', $user->password));
        $this->assertNotEmpty($user->username);
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('mcmc:create-admin', ['--name' => 'First Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (min 12 characters, mixed case and numbers)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_it_rejects_a_mismatched_confirmation(): void
    {
        $this->artisan('mcmc:create-admin', ['--name' => 'First Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (min 12 characters, mixed case and numbers)', 'StrongPass1234')
            ->expectsQuestion('Confirm password', 'StrongPass9999')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_it_rejects_an_email_that_is_already_used(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $this->artisan('mcmc:create-admin', ['--name' => 'Second Admin', '--email' => 'admin@example.test'])
            ->expectsQuestion('Password (min 12 characters, mixed case and numbers)', 'StrongPass1234')
            ->expectsQuestion('Confirm password', 'StrongPass1234')
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'admin@example.test')->count());
    }
}
