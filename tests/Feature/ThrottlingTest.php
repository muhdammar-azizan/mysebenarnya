<?php

namespace Tests\Feature;

use App\Enums\InquiryCategory;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use App\Support\Throttle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ThrottlingTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email)
    {
        return Volt::test('pages.auth.register')
            ->set('name', 'Test Person')
            ->set('email', $email)
            ->set('password', 'Password1!')
            ->set('password_confirmation', 'Password1!')
            ->set('terms', true)
            ->call('register');
    }

    public function test_registration_is_limited_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->register("person{$i}@example.test")->assertHasNoErrors();
            Auth::logout();
        }

        $this->register('person6@example.test')->assertHasErrors('email');

        $this->assertSame(5, User::count());
    }

    public function test_forgot_password_is_limited_per_ip_and_per_inbox(): void
    {
        Notification::fake();
        // Laravel's own broker also refuses a second link within 60 seconds; switch that off to test our limit.
        config(['auth.passwords.users.throttle' => 0]);
        User::factory()->create(['email' => 'victim@example.test']);
        User::factory()->create(['email' => 'other@example.test']);

        // One inbox can only be emailed 3 times an hour.
        for ($i = 0; $i < 3; $i++) {
            Volt::test('pages.auth.forgot-password')->set('email', 'victim@example.test')->call('sendPasswordResetLink')->assertHasNoErrors('email');
        }
        Volt::test('pages.auth.forgot-password')->set('email', 'victim@example.test')->call('sendPasswordResetLink')->assertHasErrors('email');

        // A different inbox is still allowed from this IP.
        Volt::test('pages.auth.forgot-password')->set('email', 'other@example.test')->call('sendPasswordResetLink')->assertHasNoErrors('email');
    }

    public function test_forgot_password_is_limited_per_ip_across_many_inboxes(): void
    {
        Notification::fake();

        foreach (range(1, 6) as $i) {
            User::factory()->create(['email' => "person{$i}@example.test"]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Volt::test('pages.auth.forgot-password')->set('email', "person{$i}@example.test")->call('sendPasswordResetLink')->assertHasNoErrors('email');
        }

        Volt::test('pages.auth.forgot-password')->set('email', 'person6@example.test')->call('sendPasswordResetLink')->assertHasErrors('email');
    }

    public function test_sign_in_failures_are_limited_per_ip_even_when_the_username_changes(): void
    {
        config(['security.limits.login_ip' => [3, 600]]);

        foreach (['a@example.test', 'b@example.test', 'c@example.test'] as $email) {
            Volt::test('pages.auth.login')->set('form.email', $email)->set('form.password', 'wrong')->call('login')->assertHasErrors('form.email');
        }

        $component = Volt::test('pages.auth.login')->set('form.email', 'd@example.test')->set('form.password', 'wrong')->call('login');

        $this->assertStringContainsString('Too many login attempts', $component->errors()->first('form.email'));
    }

    public function test_submitting_inquiries_is_limited(): void
    {
        config(['security.limits.submit_inquiry' => [2, 3600]]);

        $user = User::factory()->create(['role' => UserRole::Public]);

        foreach (range(1, 2) as $i) {
            Volt::actingAs($user)->test('public.inquiries.create')
                ->set('title', "Claim number {$i}")
                ->set('category', InquiryCategory::FinancialScams->value)
                ->set('description', 'This is a sufficiently long description of the claim being reported.')
                ->set('source_url', 'https://example.com/post')
                ->call('submit')
                ->assertHasNoErrors();
        }

        $third = Volt::actingAs($user)->test('public.inquiries.create')
            ->set('title', 'Claim number 3')
            ->set('category', InquiryCategory::FinancialScams->value)
            ->set('description', 'This is a sufficiently long description of the claim being reported.')
            ->set('source_url', 'https://example.com/post')
            ->call('submit')
            ->assertNoRedirect();

        $this->assertSame(2, Inquiry::count());
        $third->assertSee('Too many attempts');
    }

    public function test_exports_are_limited(): void
    {
        config(['security.limits.export' => [1, 60]]);

        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)->test('mcmc.users.index')->call('exportCsv')->assertStatus(200);

        Volt::actingAs($staff)->test('mcmc.users.index')->call('exportCsv')->assertStatus(429);
    }

    public function test_live_search_goes_quiet_when_over_the_limit(): void
    {
        config(['security.limits.search' => [2, 60]]);

        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['title' => 'Zebra claim about prices']);

        $component = Volt::actingAs($staff)->test('mcmc.global-search');

        $component->set('query', 'Zeb')->assertSee('Zebra claim');
        $component->set('query', 'Zebr')->assertSee('Zebra claim');
        $component->set('query', 'Zebra')->assertDontSee('Zebra claim');
    }

    public function test_switching_language_is_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('locale.update', 'ms'))->assertRedirect();
        }

        $this->post(route('locale.update', 'ms'))->assertStatus(429);
    }

    public function test_the_wait_message_is_translated(): void
    {
        $this->assertSame('Too many attempts. Please try again in about 2 minute(s).', Throttle::message(100));

        app()->setLocale('ms');

        $this->assertSame('Terlalu banyak percubaan. Sila cuba lagi dalam kira-kira 2 minit.', Throttle::message(100));
        $this->assertSame('Terlalu banyak percubaan. Sila cuba lagi dalam kira-kira 1 minit.', Throttle::message(5));
    }

    public function test_limits_are_per_ip_not_per_account(): void
    {
        config(['security.limits.search' => [1, 60]]);

        $staff = User::factory()->mcmcStaff()->create();

        $this->assertNull(Throttle::limited('search', '203.0.113.1|'.$staff->id));
        $this->assertNotNull(Throttle::limited('search', '203.0.113.1|'.$staff->id));
        // Another visitor on the same shared account is not affected.
        $this->assertNull(Throttle::limited('search', '203.0.113.2|'.$staff->id));
    }
}
