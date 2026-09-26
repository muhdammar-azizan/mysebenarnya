<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('TempPassw0rd!'),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get(route('password.force-change'));

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.force-password-change');
    }

    public function test_user_must_change_password_is_redirected_here_from_other_routes(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('password.force-change'));
    }

    public function test_password_can_be_updated_with_correct_temporary_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('TempPassw0rd!'),
            'must_change_password' => true,
        ]);

        Volt::actingAs($user)
            ->test('pages.auth.force-password-change')
            ->set('tempPassword', 'TempPassw0rd!')
            ->set('password', 'NewSecure1!')
            ->set('password_confirmation', 'NewSecure1!')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewSecure1!', $user->password));
    }

    public function test_incorrect_temporary_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('TempPassw0rd!'),
            'must_change_password' => true,
        ]);

        Volt::actingAs($user)
            ->test('pages.auth.force-password-change')
            ->set('tempPassword', 'WrongPassword!')
            ->set('password', 'NewSecure1!')
            ->set('password_confirmation', 'NewSecure1!')
            ->call('updatePassword')
            ->assertHasErrors(['tempPassword']);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('TempPassw0rd!'),
            'must_change_password' => true,
        ]);

        Volt::actingAs($user)
            ->test('pages.auth.force-password-change')
            ->set('tempPassword', 'TempPassw0rd!')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('updatePassword')
            ->assertHasErrors(['password']);

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
