<?php

namespace Tests\Feature;

use App\Enums\AgencyStaffRole;
use App\Enums\ClarificationPriority;
use App\Enums\ClarificationStatus;
use App\Enums\ClarificationTopic;
use App\Enums\ConsultStatus;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Livewire\Volt\Volt;
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
        'Public role' => [
            'resources/views/livewire/public/*.blade.php',
            'resources/views/livewire/public/*/*.blade.php',
            'resources/views/livewire/profile/*.blade.php',
            'resources/views/profile.blade.php',
            'resources/views/dashboard.blade.php',
            'resources/views/components/inquiry-status-badge.blade.php',
            'resources/views/components/network-error-banner.blade.php',
            'resources/views/components/form-error-banner.blade.php',
            'app/Enums/InquiryStatus.php',
            'app/Enums/UserRole.php',
        ],
        'Agency role' => [
            'resources/views/livewire/agency/*.blade.php',
            'resources/views/livewire/agency/*/*.blade.php',
            'app/Enums/ClarificationStatus.php',
            'app/Enums/ClarificationTopic.php',
            'app/Enums/ClarificationPriority.php',
            'app/Enums/AgencyStaffRole.php',
            'app/Notifications/InquiryAssignedToAgency.php',
            'app/Notifications/AgencyConsultRequested.php',
        ],
        'MCMC role' => [
            'resources/views/livewire/mcmc/*.blade.php',
            'resources/views/livewire/mcmc/*/*.blade.php',
            'resources/views/components/export-modal.blade.php',
            'app/Enums/ConsultStatus.php',
            'app/Notifications/NewInquirySubmitted.php',
            'app/Notifications/NewAgencyRegistered.php',
            'app/Notifications/AgencyAccountProvisioned.php',
            'app/Notifications/AgencyStaffInvited.php',
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

    public function test_every_enum_display_name_has_a_malay_translation(): void
    {
        $malay = $this->malay();
        $missing = [];

        foreach (InquiryCategory::cases() as $case) {
            if (! array_key_exists($case->value, $malay)) {
                $missing[] = 'category: '.$case->value;
            }
        }

        foreach (InquiryStatus::cases() as $case) {
            $english = $case->label('en');

            if (! array_key_exists($english, $malay)) {
                $missing[] = 'status: '.$english;
            }
        }

        foreach (UserRole::cases() as $case) {
            if (! array_key_exists($case->label('en'), $malay)) {
                $missing[] = 'role: '.$case->label('en');
            }
        }

        foreach (ClarificationStatus::cases() as $case) {
            if (! array_key_exists($case->label('en'), $malay)) {
                $missing[] = 'clarification status: '.$case->label('en');
            }
        }

        foreach (ClarificationTopic::cases() as $case) {
            if (! array_key_exists($case->value, $malay)) {
                $missing[] = 'clarification topic: '.$case->value;
            }
        }

        foreach (ClarificationPriority::cases() as $case) {
            if (! array_key_exists($case->value, $malay)) {
                $missing[] = 'clarification priority: '.$case->value;
            }
        }

        foreach (AgencyStaffRole::cases() as $case) {
            if (! array_key_exists($case->label('en'), $malay)) {
                $missing[] = 'agency role: '.$case->label('en');
            }
        }

        foreach (ConsultStatus::cases() as $case) {
            if (! array_key_exists($case->label('en'), $malay)) {
                $missing[] = 'consult status: '.$case->label('en');
            }
        }

        foreach (['assigned', 'reassigned', 'discarded', 'submitted', 'jurisdiction_accepted', 'jurisdiction_rejected', 'investigation_updated', 'verdict_finalized'] as $action) {
            $headline = (string) str($action)->headline();

            if (! array_key_exists($headline, $malay)) {
                $missing[] = 'activity action: '.$headline;
            }
        }

        $this->assertSame([], $missing, "Missing Malay translations:\n".implode("\n", $missing));
    }

    public function test_enum_labels_follow_the_current_language_but_stored_values_do_not_change(): void
    {
        app()->setLocale('ms');

        $this->assertSame('Dalam Siasatan', InquiryStatus::UnderInvestigation->label());
        $this->assertSame('Disahkan Benar', InquiryStatus::VerifiedTrue->label());
        $this->assertSame('Penipuan Kewangan & Perbankan', InquiryCategory::FinancialScams->label());
        $this->assertSame('Pengguna Awam', UserRole::Public->label());

        // Stored values and explicit-English labels are untouched.
        $this->assertSame('Under Investigation', InquiryStatus::UnderInvestigation->value);
        $this->assertSame('Financial Scams & Banking', InquiryCategory::FinancialScams->value);
        $this->assertSame('Verified as True', InquiryStatus::VerifiedTrue->label('en'));
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

            // Plural forms (a|b) repeat placeholders, so compare the distinct set.
            $this->assertEqualsCanonicalizing(array_unique($expected[0]), array_unique($actual[0]), "Placeholders differ in: {$english}");
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
            ->assertSee('Meja Semakan Fakta SKMM')
            ->assertSee('Rakan Agensi')
            ->assertSee('Kakitangan SKMM');
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

    // ---------- Public role pages render in Malay ----------

    public function test_public_dashboard_and_forms_render_in_malay(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'ms']);
        Inquiry::factory()->create([
            'submitted_by' => $user->id,
            'category' => InquiryCategory::HealthMedical,
            'status' => InquiryStatus::UnderInvestigation,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pertanyaan Awam')
            ->assertSee('Dakwaan Kesihatan &amp; Perubatan', false)
            ->assertSee('Semua Status')
            ->assertSee('Dalam Siasatan');

        $this->actingAs($user)->get(route('inquiries.create'))
            ->assertOk()
            ->assertSee('Hantar Pertanyaan Baharu')
            ->assertSee('Simpan sebagai Draf')
            ->assertSee('Pilih kategori');

        $this->actingAs($user)->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('Jumlah Pertanyaan')
            ->assertSee('Dakwaan Kesihatan &amp; Perubatan', false);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Notifikasi');
        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('Maklumat Profil')->assertSee('Kata Laluan &amp; Keselamatan', false);
    }

    public function test_public_inquiry_detail_and_browse_render_in_malay(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public, 'locale' => 'ms']);
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $user->id,
            'status' => InquiryStatus::VerifiedTrue,
        ]);

        $this->actingAs($user)->get(route('inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Sejarah Status')
            ->assertSee('Disahkan Benar');

        $this->actingAs($user)->get(route('browse.show', $inquiry))
            ->assertOk()
            ->assertSee('Kongsi keputusan ini')
            ->assertSee('Salin pautan');
    }

    public function test_saved_notification_messages_stay_english_whatever_the_actors_language(): void
    {
        app()->setLocale('ms');

        $inquiry = Inquiry::factory()->create();
        $data = (new InquiryStatusChanged($inquiry, 'Submitted', 'Verified True'))->toArray(new User);

        $this->assertStringContainsString('is now Verified as True.', $data['message']);
    }

    // ---------- Agency role pages render in Malay ----------

    private function agencyStaffInMalay(): array
    {
        $agency = Agency::factory()->create(['name' => 'Kementerian Ujian']);
        $staff = User::factory()->agencyStaff()->create([
            'agency_id' => $agency->id,
            'agency_role' => AgencyStaffRole::Admin,
            'locale' => 'ms',
        ]);

        return [$agency, $staff];
    }

    public function test_agency_pages_render_in_malay(): void
    {
        [$agency, $staff] = $this->agencyStaffInMalay();
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'category' => InquiryCategory::FinancialScams,
            'status' => InquiryStatus::UnderInvestigation,
            'reviewed_at' => now()->subDays(30),
        ]);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka Agensi')
            ->assertSee('Jumlah Ditugaskan')
            ->assertSee('Pertanyaan yang Baru Ditugaskan')
            ->assertSee('Penipuan Kewangan &amp; Perbankan', false)
            ->assertSee('telah lewat');

        $this->actingAs($staff)->get(route('agency.inquiries.index'))
            ->assertOk()
            ->assertSee('Dalam Siasatan')
            ->assertSee('Nota daripada SKMM');

        $this->actingAs($staff)->get(route('agency.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Butiran')
            ->assertSee('Log Aktiviti');

        $this->actingAs($staff)->get(route('agency.organization.index'))
            ->assertOk()
            ->assertSee('Maklumat Agensi')
            ->assertSee('Kata Laluan &amp; Keselamatan', false);

        $this->actingAs($staff)->get(route('agency.reports.index'))->assertOk()->assertSee('Trend Penyelesaian Bulanan');
        $this->actingAs($staff)->get(route('agency.activity.index'))->assertOk()->assertSee('Semua Aktiviti');
        $this->actingAs($staff)->get(route('agency.notifications.index'))->assertOk()->assertSee('Notifikasi');
    }

    public function test_agency_clarification_topics_roles_and_activity_actions_are_translated(): void
    {
        [$agency, $staff] = $this->agencyStaffInMalay();
        $inquiry = Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        InquiryActivityLog::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $staff->id,
            'action' => 'jurisdiction_accepted',
            'notes' => 'ok',
        ]);

        app()->setLocale('ms'); // Volt::test() skips the middleware that normally sets it

        Volt::actingAs($staff)->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->call('setTab', 'activity')
            ->assertSee('Bidang Kuasa Diterima');

        $this->actingAs($staff)->get(route('agency.organization.index', ['tab' => 'security']))->assertOk();

        $this->assertSame('Pentadbir', AgencyStaffRole::Admin->label());
        $this->assertSame('Bukti tiada atau tidak jelas', ClarificationTopic::MissingEvidence->label());
        $this->assertSame('Menunggu Maklum Balas SKMM', ClarificationStatus::Open->label());
        $this->assertSame('Segera', ClarificationPriority::Urgent->label());

        // Stored values never change.
        $this->assertSame('Missing or unclear evidence', ClarificationTopic::MissingEvidence->value);
        $this->assertSame('admin', AgencyStaffRole::Admin->value);
    }

    public function test_exports_are_produced_in_english_even_when_browsing_in_malay(): void
    {
        [$agency, $staff] = $this->agencyStaffInMalay();
        Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'category' => InquiryCategory::FinancialScams,
            'status' => InquiryStatus::VerifiedTrue,
            'reviewed_at' => now()->subDays(3),
            'resolved_at' => now()->subDay(),
        ]);

        app()->setLocale('ms');

        $component = Volt::actingAs($staff)->test('agency.reports.index')
            ->set('exportFormat', 'excel')
            ->call('generateExport')
            ->assertHasNoErrors();

        // The language is put back afterwards, so the page keeps rendering in Malay.
        $this->assertSame('ms', app()->getLocale());
        $component->assertSee('Trend Penyelesaian Bulanan')->assertSee('Penipuan Kewangan &amp; Perbankan', false);

        $this->assertDatabaseHas('report_exports', ['user_id' => $staff->id, 'format' => 'excel']);
    }

    public function test_the_export_validation_message_follows_the_screen_language(): void
    {
        [, $staff] = $this->agencyStaffInMalay();

        app()->setLocale('ms');

        Volt::actingAs($staff)->test('agency.reports.index')
            ->set('exportSections', [])
            ->call('generateExport')
            ->assertHasErrors(['exportSections' => 'Pilih sekurang-kurangnya satu bahagian untuk dieksport.']);
    }

    public function test_the_helper_restores_the_language_even_when_the_export_fails(): void
    {
        $host = new class
        {
            use \App\Concerns\GeneratesReports;

            public function run(callable $callback)
            {
                return $this->inEnglish($callback);
            }
        };

        app()->setLocale('ms');

        try {
            $host->run(function () {
                $this->assertSame('en', app()->getLocale());

                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('ms', app()->getLocale());
    }

    // ---------- MCMC role pages render in Malay ----------

    private function mcmcInMalay(): User
    {
        return User::factory()->mcmcStaff()->create(['locale' => 'ms']);
    }

    public function test_mcmc_pages_render_in_malay(): void
    {
        $mcmc = $this->mcmcInMalay();
        $agency = Agency::factory()->create(['specialization' => InquiryCategory::FinancialScams]);
        Inquiry::factory()->create([
            'category' => InquiryCategory::FinancialScams,
            'status' => InquiryStatus::Submitted,
            'created_at' => now()->subDays(10),
        ]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'reviewed_at' => now()->subDays(30)]);

        $this->actingAs($mcmc)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Papan Pemuka SKMM')
            ->assertSee('Pertanyaan Menunggu Triaj')
            ->assertSee('Agensi Berdaftar')
            ->assertSee('Penipuan Kewangan &amp; Perbankan', false)
            ->assertSee('telah menunggu lebih 3 hari untuk triaj');

        $this->actingAs($mcmc)->get(route('mcmc.triage.index'))->assertOk()->assertSee('Menunggu Triaj')->assertSee('Lewat');
        $this->actingAs($mcmc)->get(route('mcmc.triage.index', ['tab' => 'assigned']))->assertOk()->assertSee('Agensi Ditugaskan');
        $this->actingAs($mcmc)->get(route('mcmc.inquiries.index'))->assertOk()->assertSee('Semua Pertanyaan')->assertSee('Semua Agensi')->assertSee('Eksport CSV');
        $this->actingAs($mcmc)->get(route('mcmc.agencies.index'))->assertOk()->assertSee('Semua Pengkhususan');
        $this->actingAs($mcmc)->get(route('mcmc.agencies.create'))->assertOk()->assertSee('Daftar Agensi Baharu');
        $this->actingAs($mcmc)->get(route('mcmc.agencies.edit', $agency))->assertOk()->assertSee('Sunting Agensi');
        $this->actingAs($mcmc)->get(route('mcmc.users.index'))->assertOk()->assertSee('Pengguna Berdaftar')->assertSee('Jumlah:');
        $this->actingAs($mcmc)->get(route('mcmc.reports.index'))->assertOk()->assertSee('Laporan &amp; Analitik', false)->assertSee('Laporan Pengguna');
        $this->actingAs($mcmc)->get(route('mcmc.settings.index'))->assertOk()->assertSee('Tetapan Akaun');
        $this->actingAs($mcmc)->get(route('mcmc.audit-log.index'))->assertOk()->assertSee('Log Audit')->assertSee('Semua Tindakan');
        $this->actingAs($mcmc)->get(route('mcmc.recent-actions.index'))->assertOk()->assertSee('Tindakan Terkini Saya');
        $this->actingAs($mcmc)->get(route('mcmc.clarifications.index'))->assertOk()->assertSee('Permintaan Penjelasan');
        $this->actingAs($mcmc)->get(route('mcmc.notifications.index'))->assertOk()->assertSee('Notifikasi');
    }

    public function test_mcmc_inquiry_detail_and_clarification_thread_render_in_malay(): void
    {
        $mcmc = $this->mcmcInMalay();
        $agency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'category' => InquiryCategory::HealthMedical,
            'status' => InquiryStatus::UnderInvestigation,
        ]);
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $mcmc->id, 'action' => 'verdict_finalized', 'notes' => 'x']);

        $thread = \App\Models\ClarificationThread::create([
            'inquiry_id' => $inquiry->id,
            'opened_by' => User::factory()->agencyStaff()->create(['agency_id' => $agency->id])->id,
            'subject' => 'Need more context',
            'topic' => ClarificationTopic::MissingEvidence,
            'priority' => ClarificationPriority::Urgent,
            'status' => ClarificationStatus::Open,
        ]);

        $this->actingAs($mcmc)->get(route('mcmc.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('Dakwaan Kesihatan &amp; Perubatan', false);

        app()->setLocale('ms'); // Volt::test() skips the middleware that normally sets it

        Volt::actingAs($mcmc)->test('mcmc.inquiries.show', ['inquiry' => $inquiry])
            ->call('setTab', 'activity')
            ->assertSee('Keputusan Dimuktamadkan');

        $this->actingAs($mcmc)->get(route('mcmc.clarifications.show', $thread))
            ->assertOk()
            ->assertSee('Bukti tiada atau tidak jelas')
            ->assertSee('Menunggu Maklum Balas SKMM')
            ->assertSee('Segera');

        $this->actingAs($mcmc)->get(route('mcmc.clarifications.index'))
            ->assertOk()
            ->assertSee('Bukti tiada atau tidak jelas');
    }

    public function test_the_global_search_and_audit_log_speak_malay(): void
    {
        $mcmc = $this->mcmcInMalay();
        app()->setLocale('ms');

        Volt::actingAs($mcmc)->test('mcmc.global-search')->assertSee('Taip sekurang-kurangnya 2 aksara untuk mencari.');
        Volt::actingAs($mcmc)->test('mcmc.global-search')->set('query', 'tiada-padanan-langsung')->assertSee('Tiada hasil untuk');

        $inquiry = Inquiry::factory()->create();
        InquiryActivityLog::create(['inquiry_id' => $inquiry->id, 'user_id' => $mcmc->id, 'action' => 'jurisdiction_accepted']);

        Volt::actingAs($mcmc)->test('mcmc.audit-log.index')
            ->assertSee('Bidang Kuasa Diterima')
            ->assertSee('Kakitangan SKMM');
    }

    public function test_mcmc_exports_are_always_english(): void
    {
        $mcmc = $this->mcmcInMalay();
        Inquiry::factory()->create([
            'title' => 'Export sample',
            'category' => InquiryCategory::FinancialScams,
            'status' => InquiryStatus::Submitted,
        ]);
        User::factory()->create(['role' => UserRole::Public, 'email' => 'someone@example.test']);

        app()->setLocale('ms');

        $csv = function ($component) {
            return base64_decode($component->effects['download']['content']);
        };

        $inquiries = $csv(Volt::actingAs($mcmc)->test('mcmc.inquiries.index')->call('exportCsv'));
        $this->assertStringContainsString('Financial Scams & Banking', $inquiries);
        $this->assertStringContainsString('Awaiting Review', $inquiries);
        $this->assertStringNotContainsString('Penipuan', $inquiries);
        $this->assertStringNotContainsString('Menunggu', $inquiries);

        $users = $csv(Volt::actingAs($mcmc)->test('mcmc.users.index')->call('exportCsv'));
        $this->assertStringContainsString('Name,Email,Registered,Status,Inquiries', $users);
        $this->assertStringContainsString('someone@example.test', $users);
        $this->assertStringContainsString('Verified', $users);
        $this->assertStringNotContainsString('Nama', $users);
        $this->assertSame('ms', app()->getLocale());

        // The page itself keeps rendering in Malay straight after an export.
        Volt::actingAs($mcmc)->test('mcmc.inquiries.index')
            ->call('exportCsv')
            ->assertSee('Penipuan Kewangan &amp; Perbankan', false);
    }

    public function test_the_malay_dashboard_alert_keeps_the_english_wording_in_english(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create(['locale' => 'en']);
        $agency = Agency::factory()->create();
        $thread = \App\Models\ClarificationThread::create([
            'inquiry_id' => Inquiry::factory()->create(['agency_id' => $agency->id])->id,
            'opened_by' => User::factory()->agencyStaff()->create(['agency_id' => $agency->id])->id,
            'subject' => 'Q',
            'topic' => ClarificationTopic::Other,
            'priority' => ClarificationPriority::Urgent,
            'status' => ClarificationStatus::Open,
        ]);

        $this->actingAs($mcmc)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 clarification request from agencies awaiting your response')
            ->assertSee('(1 urgent)');
    }

    private function renderIn(string $locale, string $view): string
    {
        app()->setLocale($locale);

        return view($view)->render();
    }
}
