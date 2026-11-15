<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Malay translation: every string wrapped in __() inside the areas
 * that have been translated so far must have an entry in lang/ms.json. New
 * areas are added to SCOPES as each translation step is completed.
 */
class TranslationCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Translated areas (paths relative to the project root, globs allowed).
     *
     * @var array<string, list<string>>
     */
    private const SCOPES = [
        'Public pages, login and errors' => [
            'resources/views/welcome.blade.php',
            'resources/views/about.blade.php',
            'resources/views/faq.blade.php',
            'resources/views/track.blade.php',
            'resources/views/components/info-layout.blade.php',
            'resources/views/components/error-page.blade.php',
            'resources/views/components/language-switcher.blade.php',
            'resources/views/components/auth-card.blade.php',
            'resources/views/errors/*.blade.php',
            'resources/views/livewire/pages/auth/*.blade.php',
            'app/Http/Controllers/TrackInquiryController.php',
            'app/Livewire/Forms/LoginForm.php',
        ],
        'Navigation and header' => [
            'resources/views/components/*-sidebar.blade.php',
            'resources/views/layouts/*.blade.php',
            'resources/views/livewire/*/topbar-widgets.blade.php',
        ],
    ];

    /**
     * @return array<string, string>
     */
    private function malay(): array
    {
        return json_decode(file_get_contents(lang_path('ms.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $patterns
     * @return array<string, list<string>> string => files that use it
     */
    private function stringsUsedIn(array $patterns): array
    {
        $found = [];

        foreach ($patterns as $pattern) {
            foreach (glob(base_path($pattern)) ?: [] as $file) {
                $source = file_get_contents($file);

                preg_match_all('~(?:__|trans|trans_choice)\(\s*(\x27(?:[^\x27\x5c]|\x5c.)*\x27|"(?:[^"\x5c]|\x5c.)*")~s', $source, $matches);

                foreach ($matches[1] as $literal) {
                    $value = substr($literal, 1, -1);
                    $value = $literal[0] === "'"
                        ? str_replace(["\\'", '\\\\'], ["'", '\\'], $value)
                        : stripcslashes($value);

                    // Keys that point at Laravel's PHP lang files (auth.failed ...) are not JSON strings.
                    if (preg_match('/^[a-z_]+\.[a-z_.]+$/', $value)) {
                        continue;
                    }

                    $found[$value][] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                }
            }
        }

        return $found;
    }

    public function test_every_translated_area_has_a_malay_entry_for_each_string(): void
    {
        $malay = $this->malay();
        $missing = [];

        foreach (self::SCOPES as $scope => $patterns) {
            foreach ($this->stringsUsedIn($patterns) as $string => $files) {
                if (! array_key_exists($string, $malay)) {
                    $missing[] = "[{$scope}] \"{$string}\" ({$files[0]})";
                }
            }
        }

        $this->assertSame([], $missing, "Missing Malay translations:\n".implode("\n", $missing));
    }

    public function test_every_scope_pattern_matches_at_least_one_file(): void
    {
        foreach (self::SCOPES as $scope => $patterns) {
            foreach ($patterns as $pattern) {
                $this->assertNotEmpty(glob(base_path($pattern)), "Pattern \"{$pattern}\" in scope \"{$scope}\" matches no files.");
            }
        }
    }

    public function test_placeholders_are_kept_in_every_translation(): void
    {
        foreach ($this->malay() as $english => $malay) {
            preg_match_all('/:[a-z_]+/i', $english, $expected);
            preg_match_all('/:[a-z_]+/i', $malay, $actual);

            $this->assertEqualsCanonicalizing($expected[0], $actual[0], "Placeholders differ in: {$english}");
        }
    }

    public function test_there_are_no_unused_translations_for_the_checked_areas_only_by_accident(): void
    {
        // Sanity: the file is sorted so merges stay readable.
        $keys = array_keys($this->malay());
        $sorted = $keys;
        usort($sorted, fn ($a, $b) => strcasecmp($a, $b));

        $this->assertSame($sorted, $keys, 'lang/ms.json should stay sorted by key (case-insensitive).');
    }

    // ---------- Pages actually render in Malay ----------

    public function test_the_landing_page_renders_in_malay(): void
    {
        $this->withSession(['locale' => 'ms'])->get('/')
            ->assertOk()
            ->assertSee('Platform Pengesahan Rasmi SKMM')
            ->assertSee('Cipta Akaun')
            ->assertDontSee('Official MCMC Verification Platform');
    }

    public function test_the_login_page_renders_in_malay_for_every_portal(): void
    {
        $this->withSession(['locale' => 'ms'])->get(route('login'))
            ->assertOk()
            ->assertSee('Selamat Kembali')
            ->assertSee('Lupa Kata Laluan')
            ->assertSee('Ingat Saya')
            ->assertSee('Log masuk dengan Google')
            ->assertSee('Meja Semakan Fakta SKMM');
    }

    public function test_the_register_and_reset_pages_render_in_malay(): void
    {
        $this->withSession(['locale' => 'ms'])->get(route('register'))->assertOk()->assertSee('Cipta kata laluan');
        $this->withSession(['locale' => 'ms'])->get(route('password.request'))->assertOk()->assertSee('Hantar Pautan Set Semula');
    }

    public function test_faq_about_and_track_render_in_malay(): void
    {
        $this->withSession(['locale' => 'ms'])->get(route('faq'))->assertOk()->assertSee('Cara mengenal berita palsu');
        $this->withSession(['locale' => 'ms'])->get(route('about'))->assertOk()->assertSee('Tentang MySebenarnya');
        $this->withSession(['locale' => 'ms'])->get(route('track'))->assertOk()->assertSee('Jejak Pertanyaan');
        $this->withSession(['locale' => 'ms'])->get(route('track', ['ref' => 'bad']))->assertOk()->assertSee('nombor rujukan yang sah');
    }

    public function test_error_pages_render_in_malay_including_a_real_404(): void
    {
        config(['app.debug' => false]);

        $this->withSession(['locale' => 'ms'])->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemui');

        $this->assertStringContainsString('Akses Dinafikan', $this->renderIn('ms', 'errors.403'));
        $this->assertStringContainsString('Sesuatu Tidak Kena', $this->renderIn('ms', 'errors.500'));
        $this->assertStringContainsString('Dalam Penyelenggaraan', $this->renderIn('ms', 'errors.503'));
        $this->assertStringContainsString('Terlalu Banyak Permintaan', $this->renderIn('ms', 'errors.429'));
    }

    public function test_login_error_messages_are_translated(): void
    {
        $this->withSession(['locale' => 'ms']);
        app()->setLocale('ms');

        $this->assertSame('Maklumat log masuk ini tidak sepadan dengan rekod kami.', trans('auth.failed'));
        $this->assertStringContainsString('Terlalu banyak percubaan', trans('auth.throttle', ['seconds' => 5]));
        $this->assertSame('Kata laluan tidak betul.', trans('validation.current_password'));
    }

    public function test_validation_messages_and_field_names_use_malay(): void
    {
        app()->setLocale('ms');

        $validator = validator(['email' => ''], ['email' => 'required']);

        $this->assertSame('Medan e-mel diperlukan.', $validator->errors()->first('email') ?: ($validator->fails() ? $validator->errors()->first('email') : ''));
    }

    public function test_english_is_unchanged_when_the_language_is_english(): void
    {
        $this->get('/')->assertOk()->assertSee('Official MCMC Verification Platform');
        $this->get(route('login'))->assertOk()->assertSee('Welcome Back');
    }

    public function test_signed_in_users_still_get_their_own_language_on_error_pages(): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'ms']);

        $this->actingAs($user)->get('/nowhere-at-all')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemui')
            ->assertSee(route('dashboard'), false);
    }

    private function renderIn(string $locale, string $view): string
    {
        app()->setLocale($locale);

        return view($view)->render();
    }
}
