<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /**
     * A staff user who already has 2FA switched on.
     */
    private function staffWithTwoFactor(array $attributes = []): User
    {
        $secret = app(TwoFactorAuthentication::class)->generateSecret();

        return User::factory()->mcmcStaff()->create(array_merge([
            'username' => 'mcmc.secure',
            'password' => 'Passw0rd!secure',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
        ], $attributes));
    }

    private function startChallenge(User $user): void
    {
        session(['two_factor' => ['id' => $user->id, 'remember' => false, 'started_at' => now()->timestamp]]);
    }

    // ---------- Login flow ----------

    public function test_staff_without_two_factor_log_in_normally(): void
    {
        $user = User::factory()->mcmcStaff()->create(['username' => 'plain.staff', 'password' => 'Passw0rd!plain']);

        Volt::test('pages.auth.login')
            ->call('setRole', 'mcmc_staff')
            ->set('form.email', 'plain.staff')
            ->set('form.password', 'Passw0rd!plain')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_with_two_factor_are_sent_to_the_challenge_and_not_logged_in(): void
    {
        $user = $this->staffWithTwoFactor();

        Volt::test('pages.auth.login')
            ->call('setRole', 'mcmc_staff')
            ->set('form.email', 'mcmc.secure')
            ->set('form.password', 'Passw0rd!secure')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('two-factor.challenge', absolute: false));

        $this->assertGuest();
        $this->assertSame($user->id, session('two_factor.id'));
    }

    public function test_wrong_password_never_reaches_the_challenge(): void
    {
        $this->staffWithTwoFactor();

        Volt::test('pages.auth.login')
            ->call('setRole', 'mcmc_staff')
            ->set('form.email', 'mcmc.secure')
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertNull(session('two_factor'));
        $this->assertGuest();
    }

    // ---------- Challenge ----------

    public function test_challenge_page_without_a_pending_login_redirects_to_login(): void
    {
        Volt::test('pages.auth.two-factor-challenge')
            ->assertRedirect(route('login', absolute: false));
    }

    public function test_a_valid_code_completes_the_login(): void
    {
        $user = $this->staffWithTwoFactor();
        $this->startChallenge($user);

        Volt::test('pages.auth.two-factor-challenge')
            ->set('code', $this->currentCode($user->two_factor_secret))
            ->call('verify')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('two_factor'));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $user = $this->staffWithTwoFactor();
        $this->startChallenge($user);

        Volt::test('pages.auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors('code');

        $this->assertGuest();
    }

    public function test_the_same_code_cannot_be_used_twice(): void
    {
        $user = $this->staffWithTwoFactor();
        $code = $this->currentCode($user->two_factor_secret);

        $this->startChallenge($user);
        Volt::test('pages.auth.two-factor-challenge')->set('code', $code)->call('verify')->assertHasNoErrors();
        auth()->logout();

        $this->startChallenge($user);
        Volt::test('pages.auth.two-factor-challenge')->set('code', $code)->call('verify')->assertHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->staffWithTwoFactor();
        $this->startChallenge($user);

        Volt::test('pages.auth.two-factor-challenge')
            ->call('toggleMode')
            ->set('code', 'AAAAA-BBBBB')
            ->call('verify')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['ccccc-ddddd'], $user->fresh()->two_factor_recovery_codes);

        auth()->logout();
        $this->startChallenge($user);

        Volt::test('pages.auth.two-factor-challenge')
            ->call('toggleMode')
            ->set('code', 'aaaaa-bbbbb')
            ->call('verify')
            ->assertHasErrors('code');

        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_lock_the_challenge(): void
    {
        $user = $this->staffWithTwoFactor();
        $this->startChallenge($user);

        $component = Volt::test('pages.auth.two-factor-challenge');

        for ($i = 0; $i < 5; $i++) {
            $component->set('code', '000000')->call('verify')->assertHasErrors('code');
        }

        // Even the right code is refused while locked out.
        $component->set('code', $this->currentCode($user->two_factor_secret))->call('verify')->assertHasErrors('code');

        $this->assertGuest();
    }

    public function test_an_old_pending_login_expires(): void
    {
        $user = $this->staffWithTwoFactor();
        session(['two_factor' => ['id' => $user->id, 'remember' => false, 'started_at' => now()->subMinutes(11)->timestamp]]);

        Volt::test('pages.auth.two-factor-challenge')
            ->assertRedirect(route('login', absolute: false));
    }

    // ---------- Setup in the profile ----------

    public function test_public_users_cannot_use_the_setup_component(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->assertForbidden();
    }

    public function test_staff_can_enable_two_factor_with_a_valid_code(): void
    {
        $user = User::factory()->mcmcStaff()->create();

        $component = Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('enable');

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled());

        $component->set('code', $this->currentCode($user->two_factor_secret))
            ->call('confirm')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertCount(TwoFactorAuthentication::RECOVERY_CODE_COUNT, $user->two_factor_recovery_codes);
        $component->assertSet('shownRecoveryCodes', $user->two_factor_recovery_codes);
    }

    public function test_a_wrong_code_does_not_enable_two_factor(): void
    {
        $user = User::factory()->mcmcStaff()->create();

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('enable')
            ->set('code', '123456')
            ->call('confirm')
            ->assertHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_cancelling_setup_discards_the_pending_secret(): void
    {
        $user = User::factory()->mcmcStaff()->create();

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('enable')
            ->call('cancelSetup');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_disabling_requires_the_current_password(): void
    {
        $user = $this->staffWithTwoFactor();

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('startConfirming', 'disable')
            ->set('password', 'not-the-password')
            ->call('disable')
            ->assertHasErrors('password');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('startConfirming', 'disable')
            ->set('password', 'Passw0rd!secure')
            ->call('disable')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->hasTwoFactorEnabled());
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_regenerating_recovery_codes_requires_the_password_and_replaces_the_codes(): void
    {
        $user = $this->staffWithTwoFactor();

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('startConfirming', 'regenerate')
            ->set('password', 'bad')
            ->call('regenerate')
            ->assertHasErrors('password');

        $this->assertSame(['aaaaa-bbbbb', 'ccccc-ddddd'], $user->fresh()->two_factor_recovery_codes);

        Volt::actingAs($user)->test('profile.two-factor-authentication')
            ->call('startConfirming', 'regenerate')
            ->set('password', 'Passw0rd!secure')
            ->call('regenerate')
            ->assertHasNoErrors();

        $this->assertCount(TwoFactorAuthentication::RECOVERY_CODE_COUNT, $user->fresh()->two_factor_recovery_codes);
        $this->assertNotContains('aaaaa-bbbbb', $user->fresh()->two_factor_recovery_codes);
    }

    // ---------- Storage / exposure ----------

    public function test_the_secret_and_codes_are_encrypted_in_the_database_and_hidden_from_arrays(): void
    {
        $user = $this->staffWithTwoFactor();

        $raw = \DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($user->two_factor_secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString('aaaaa-bbbbb', $raw->two_factor_recovery_codes);

        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $user->toArray());
    }

    public function test_security_tabs_show_two_factor_for_staff_and_the_profile_page_hides_it_from_public(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create();
        $this->actingAs($mcmc)->get(route('profile'))->assertOk()->assertSee('Two-Factor Authentication');

        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $this->actingAs($agencyStaff)->get(route('profile'))->assertOk()->assertSee('Two-Factor Authentication');

        $public = User::factory()->create(['role' => UserRole::Public]);
        $this->actingAs($public)->get(route('profile'))->assertOk()->assertDontSee('Two-Factor Authentication');
    }
}
