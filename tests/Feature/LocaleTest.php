<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_language_is_english(): void
    {
        $this->get(route('faq'))->assertOk()->assertSee('<html lang="en"', false)->assertSee('Frequently Asked Questions');
    }

    public function test_a_guest_can_switch_to_malay_and_it_is_remembered_in_the_session(): void
    {
        $this->post(route('locale.update', 'ms'))->assertRedirect('/');

        $this->assertSame('ms', session('locale'));

        $this->get(route('about'))->assertOk()->assertSee('<html lang="ms"', false);
    }

    public function test_unknown_languages_are_rejected(): void
    {
        $this->post(route('locale.update', 'fr'))->assertNotFound();
        $this->post(route('locale.update', '..%2Fevil'))->assertNotFound();

        $this->assertNull(session('locale'));
    }

    public function test_switching_back_returns_to_the_previous_page(): void
    {
        $this->from(route('faq'))->post(route('locale.update', 'ms'))->assertRedirect(route('faq'));
        $this->from(route('faq'))->post(route('locale.update', 'en'))->assertRedirect(route('faq'));

        $this->assertSame('en', session('locale'));
    }

    public function test_the_language_choice_is_saved_on_a_signed_in_account(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->post(route('locale.update', 'ms'));

        $this->assertSame('ms', $user->fresh()->locale);
    }

    public function test_a_saved_account_language_applies_in_a_new_session(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'ms']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="ms"', false)
            ->assertSee('Utama')
            ->assertSee('Pertanyaan Saya');
    }

    public function test_the_account_language_wins_over_the_session_language(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'en']);

        $this->actingAs($user)->withSession(['locale' => 'ms'])->get(route('dashboard'))
            ->assertSee('<html lang="en"', false);
    }

    public function test_an_invalid_stored_language_falls_back_to_the_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'xx']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_untranslated_text_falls_back_to_english(): void
    {
        app()->setLocale('ms');

        $this->assertSame('A sentence nobody has translated yet.', __('A sentence nobody has translated yet.'));
        $this->assertSame('Papan Pemuka', __('Dashboard'));
    }

    public function test_every_role_layout_shows_the_language_switcher(): void
    {
        $public = User::factory()->create(['role' => UserRole::Public]);
        $mcmc = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        foreach ([$public, $mcmc, $staff] as $user) {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee(route('locale.update', 'ms'), false);
        }

        auth()->logout();

        foreach (['login', 'register'] as $page) {
            $this->get(route($page))->assertOk()->assertSee(route('locale.update', 'ms'), false);
        }

        $this->get('/')->assertOk()->assertSee(route('locale.update', 'ms'), false);
    }

    public function test_switching_requires_a_csrf_token_in_real_requests(): void
    {
        $this->assertTrue(in_array('web', app('router')->getRoutes()->getByName('locale.update')->gatherMiddleware(), true));
    }

    public function test_the_malay_file_is_valid_json_with_no_empty_values(): void
    {
        $translations = json_decode(file_get_contents(lang_path('ms.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($translations);

        foreach ($translations as $english => $malay) {
            $this->assertIsString($malay);
            $this->assertNotSame('', trim($malay), "Empty translation for \"{$english}\".");
        }
    }
}
