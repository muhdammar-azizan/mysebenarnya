<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuditLogExportAndSearchTest extends TestCase
{
    use RefreshDatabase;

    private function mcmc(): User
    {
        return User::factory()->mcmcStaff()->create(['name' => 'Staff Siti']);
    }

    private function log(Inquiry $inquiry, User $user, string $action, array $extra = []): InquiryActivityLog
    {
        return InquiryActivityLog::create(array_merge([
            'inquiry_id' => $inquiry->id,
            'user_id' => $user->id,
            'action' => $action,
            'notes' => 'note for '.$action,
        ], $extra));
    }

    private function downloadedCsv($component): string
    {
        $download = $component->effects['download'] ?? null;
        $this->assertNotNull($download, 'Expected a file download.');

        return base64_decode($download['content']);
    }

    // ---------- Audit log ----------

    public function test_only_mcmc_staff_can_open_the_audit_log(): void
    {
        $this->actingAs($this->mcmc())->get(route('mcmc.audit-log.index'))->assertOk()->assertSee('Audit Log');

        $public = User::factory()->create(['role' => UserRole::Public]);
        $this->actingAs($public)->get(route('mcmc.audit-log.index'))->assertForbidden();

        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $this->actingAs($staff)->get(route('mcmc.audit-log.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('mcmc.audit-log.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_actions_from_every_user_not_just_the_viewer(): void
    {
        $viewer = $this->mcmc();
        $agency = Agency::factory()->create();
        $agencyUser = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'name' => 'Agency Aminah']);
        $inquiry = Inquiry::factory()->create(['title' => 'Flood rumour']);

        $this->log($inquiry, $agencyUser, 'verdict_finalized');
        $this->log($inquiry, $viewer, 'assigned');

        Volt::actingAs($viewer)->test('mcmc.audit-log.index')
            ->assertSee('Agency Aminah')
            ->assertSee('Staff Siti')
            ->assertSee('Flood rumour')
            ->assertSee('Verdict Finalized');
    }

    public function test_filters_narrow_the_log(): void
    {
        $viewer = $this->mcmc();
        $agencyUser = User::factory()->agencyStaff()->create(['agency_id' => Agency::factory()->create()->id, 'name' => 'Agency Aminah']);
        $one = Inquiry::factory()->create(['title' => 'Alpha claim']);
        $two = Inquiry::factory()->create(['title' => 'Beta claim']);

        $this->log($one, $viewer, 'assigned');
        $this->log($two, $agencyUser, 'verdict_finalized');
        $three = Inquiry::factory()->create(['title' => 'Gamma old claim']);
        $old = $this->log($three, $viewer, 'discarded');
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $component = Volt::actingAs($viewer)->test('mcmc.audit-log.index');

        $component->set('action', 'verdict_finalized')->assertSee('Beta claim')->assertDontSee('Alpha claim');
        $component->set('action', 'All')->set('actor', 'mcmc_staff')->assertSee('Alpha claim')->assertDontSee('Agency Aminah');
        $component->set('actor', 'All')->set('search', 'Alpha')->assertSee('Alpha claim')->assertDontSee('Beta claim');
        $component->set('search', $one->reference_no)->assertSee('Alpha claim')->assertDontSee('Beta claim');
        $component->set('search', '')->set('dateFrom', now()->subDays(5)->format('Y-m-d'))->assertSee('Alpha claim')->assertDontSee('Gamma old claim');
        $component->call('clearFilters')->assertSet('action', 'All')->assertSet('search', '');
    }

    public function test_audit_log_csv_export_follows_the_filters(): void
    {
        $viewer = $this->mcmc();
        $one = Inquiry::factory()->create(['title' => 'Alpha claim']);
        $two = Inquiry::factory()->create(['title' => 'Beta claim']);
        $this->log($one, $viewer, 'assigned');
        $this->log($two, $viewer, 'discarded');

        $component = Volt::actingAs($viewer)->test('mcmc.audit-log.index')
            ->set('action', 'assigned')
            ->call('exportCsv');

        $csv = $this->downloadedCsv($component);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Date/Time,User,Role', $csv);
        $this->assertStringContainsString('Alpha claim', $csv);
        $this->assertStringNotContainsString('Beta claim', $csv);
    }

    // ---------- CSV exports ----------

    public function test_all_inquiries_csv_respects_filters_and_neutralises_formulas(): void
    {
        $viewer = $this->mcmc();
        Inquiry::factory()->create(['title' => '=HYPERLINK("http://evil.example","click")']);
        Inquiry::factory()->create(['title' => 'Plain title']);

        $all = $this->downloadedCsv(Volt::actingAs($viewer)->test('mcmc.inquiries.index')->call('exportCsv'));

        // fputcsv quotes cells that contain spaces, so "Reference No" is quoted.
        $this->assertStringContainsString('"Reference No",Title,Category,Status', $all);
        $this->assertStringContainsString('Plain title', $all);
        $this->assertStringContainsString("'=HYPERLINK", $all);
        $this->assertStringNotContainsString(',"=HYPERLINK', $all);

        $filtered = $this->downloadedCsv(Volt::actingAs($viewer)->test('mcmc.inquiries.index')->set('search', 'Plain')->call('exportCsv'));

        $this->assertStringContainsString('Plain title', $filtered);
        $this->assertStringNotContainsString('HYPERLINK', $filtered);
    }

    public function test_registered_users_csv_lists_public_users_only(): void
    {
        $viewer = $this->mcmc();
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Public Pak Ali', 'email' => 'ali@example.test']);

        $csv = $this->downloadedCsv(Volt::actingAs($viewer)->test('mcmc.users.index')->call('exportCsv'));

        $this->assertStringContainsString('Name,Email,Registered,Status,Inquiries', $csv);
        $this->assertStringContainsString('ali@example.test', $csv);
        $this->assertStringNotContainsString('Staff Siti', $csv);
    }

    public function test_csv_exports_are_logged_like_other_exports(): void
    {
        $viewer = $this->mcmc();

        Volt::actingAs($viewer)->test('mcmc.users.index')->call('exportCsv');

        $this->assertDatabaseHas('report_exports', ['user_id' => $viewer->id, 'format' => 'csv']);
    }

    public function test_all_inquiries_search_also_matches_the_reference_number(): void
    {
        $viewer = $this->mcmc();
        $target = Inquiry::factory()->create(['title' => 'Unrelated headline']);
        Inquiry::factory()->create(['title' => 'Another headline']);

        Volt::actingAs($viewer)->test('mcmc.inquiries.index')
            ->set('search', $target->reference_no)
            ->assertSee('Unrelated headline')
            ->assertDontSee('Another headline');
    }

    // ---------- Global search ----------

    public function test_global_search_needs_at_least_two_characters(): void
    {
        $viewer = $this->mcmc();
        Inquiry::factory()->create(['title' => 'Alpha claim']);

        Volt::actingAs($viewer)->test('mcmc.global-search')
            ->set('query', 'A')
            ->assertSee('Type at least 2 characters')
            ->assertDontSee('Alpha claim');
    }

    public function test_global_search_finds_inquiries_agencies_and_public_users(): void
    {
        $viewer = $this->mcmc();
        $inquiry = Inquiry::factory()->create(['title' => 'Zebra vaccine rumour']);
        Agency::factory()->create(['name' => 'Zebra Health Board']);
        User::factory()->create(['role' => UserRole::Public, 'name' => 'Zebra Person', 'email' => 'zebra@example.test']);
        User::factory()->mcmcStaff()->create(['name' => 'Zebra Colleague']);

        Volt::actingAs($viewer)->test('mcmc.global-search')
            ->set('query', 'Zebra')
            ->assertSee('Zebra vaccine rumour')
            ->assertSee('Zebra Health Board')
            ->assertSee('Zebra Person')
            ->assertDontSee('Zebra Colleague');

        Volt::actingAs($viewer)->test('mcmc.global-search')
            ->set('query', $inquiry->reference_no)
            ->assertSee('Zebra vaccine rumour');
    }

    public function test_global_search_shows_a_message_when_nothing_matches(): void
    {
        Volt::actingAs($this->mcmc())->test('mcmc.global-search')
            ->set('query', 'nothing-like-this')
            ->assertSee('No results');
    }

    public function test_pressing_enter_opens_all_inquiries_with_the_search_term(): void
    {
        Volt::actingAs($this->mcmc())->test('mcmc.global-search')
            ->set('query', 'flood')
            ->call('searchAll')
            ->assertRedirect(route('mcmc.inquiries.index', ['search' => 'flood']));
    }

    public function test_limits_each_group_to_five_results(): void
    {
        $viewer = $this->mcmc();
        Inquiry::factory()->count(8)->create(['status' => InquiryStatus::Submitted, 'title' => 'Mango rumour '.fake()->unique()->numberBetween(100, 999)]);

        $component = Volt::actingAs($viewer)->test('mcmc.global-search')->set('query', 'Mango');

        $this->assertCount(5, $component->instance()->results['inquiries']);
    }

    public function test_mcmc_pages_embed_the_global_search_and_the_audit_log_link(): void
    {
        $this->actingAs($this->mcmc())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Search inquiries, agencies...')
            ->assertSee('Audit Log');
    }
}
