<?php

namespace Tests\Feature;

use App\Enums\ClarificationPriority;
use App\Enums\ClarificationStatus;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\InquirySlaReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SlaTest extends TestCase
{
    use RefreshDatabase;

    private function overdueSubmitted(): Inquiry
    {
        return Inquiry::factory()->create([
            'status' => InquiryStatus::Submitted,
            'created_at' => now()->subDays(config('sla.triage_days') + 1),
        ]);
    }

    private function overdueInvestigation(Agency $agency): Inquiry
    {
        return Inquiry::factory()->create([
            'status' => InquiryStatus::UnderInvestigation,
            'agency_id' => $agency->id,
            'reviewed_at' => now()->subDays(config('sla.investigation_days') + 1),
        ]);
    }

    // ---------- Overdue detection ----------

    public function test_triage_overdue_flags_only_old_submitted_inquiries(): void
    {
        $old = $this->overdueSubmitted();
        $fresh = Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);
        $assignedOld = Inquiry::factory()->create([
            'status' => InquiryStatus::UnderInvestigation,
            'created_at' => now()->subDays(30),
        ]);

        $ids = Inquiry::triageOverdue()->pluck('id');

        $this->assertTrue($ids->contains($old->id));
        $this->assertFalse($ids->contains($fresh->id));
        $this->assertFalse($ids->contains($assignedOld->id));
        $this->assertTrue($old->isTriageOverdue());
        $this->assertFalse($fresh->isTriageOverdue());
    }

    public function test_investigation_is_counted_from_assignment_not_submission(): void
    {
        $agency = Agency::factory()->create();

        $recentlyAssigned = Inquiry::factory()->create([
            'status' => InquiryStatus::UnderInvestigation,
            'agency_id' => $agency->id,
            'created_at' => now()->subDays(60),
            'reviewed_at' => now()->subDay(),
        ]);
        $overdue = $this->overdueInvestigation($agency);

        $ids = Inquiry::investigationOverdue()->pluck('id');

        $this->assertTrue($ids->contains($overdue->id));
        $this->assertFalse($ids->contains($recentlyAssigned->id));
        $this->assertTrue($overdue->isInvestigationOverdue());
        $this->assertFalse($recentlyAssigned->isInvestigationOverdue());
    }

    public function test_an_open_clarification_pauses_the_investigation_clock(): void
    {
        $agency = Agency::factory()->create();
        $inquiry = $this->overdueInvestigation($agency);

        ClarificationThread::create([
            'inquiry_id' => $inquiry->id,
            'opened_by' => User::factory()->agencyStaff()->create(['agency_id' => $agency->id])->id,
            'subject' => 'Need more context',
            'topic' => ClarificationTopic::SourceVerification,
            'priority' => ClarificationPriority::Normal,
            'status' => ClarificationStatus::Open,
        ]);

        $this->assertFalse(Inquiry::investigationOverdue()->pluck('id')->contains($inquiry->id));
        $this->assertFalse($inquiry->fresh()->isInvestigationOverdue());
    }

    public function test_targets_come_from_config(): void
    {
        config(['sla.triage_days' => 10]);

        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Submitted, 'created_at' => now()->subDays(5)]);

        $this->assertFalse($inquiry->isTriageOverdue());

        config(['sla.triage_days' => 2]);

        $this->assertTrue($inquiry->isTriageOverdue());
    }

    // ---------- Dashboards ----------

    public function test_mcmc_dashboard_shows_overdue_alerts(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $this->overdueSubmitted();
        $this->overdueInvestigation($agency);

        Volt::actingAs($mcmc)->test('mcmc.dashboard')
            ->assertSee('waited over 3 days for triage')
            ->assertSee('agency investigation is overdue');
    }

    public function test_mcmc_dashboard_has_no_overdue_alert_when_all_is_on_time(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        Volt::actingAs($mcmc)->test('mcmc.dashboard')
            ->assertDontSee('for triage')
            ->assertDontSee('is overdue');
    }

    public function test_agency_dashboard_only_counts_its_own_overdue_inquiries(): void
    {
        $mine = Agency::factory()->create();
        $other = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $mine->id]);

        $this->overdueInvestigation($other);

        Volt::actingAs($staff)->test('agency.dashboard')->assertDontSee('is overdue');

        $this->overdueInvestigation($mine);

        Volt::actingAs($staff)->test('agency.dashboard')->assertSee('inquiry is overdue');
    }

    public function test_overdue_chips_show_in_triage_and_agency_inbox(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $this->overdueSubmitted();
        $this->overdueInvestigation($agency);

        Volt::actingAs($mcmc)->test('mcmc.triage.index')->assertSee('Overdue');
        Volt::actingAs($staff)->test('agency.inquiries.index')->assertSee('Overdue');
    }

    // ---------- Reminder command ----------

    public function test_command_reminds_mcmc_staff_once_per_overdue_triage(): void
    {
        Notification::fake();

        $mcmc = User::factory()->mcmcStaff()->create();
        $optedOut = User::factory()->mcmcStaff()->create(['notification_preferences' => ['slaReminder' => false]]);
        $inquiry = $this->overdueSubmitted();

        $this->artisan('sla:remind')->assertSuccessful();

        Notification::assertSentTo($mcmc, InquirySlaReminder::class);
        Notification::assertNotSentTo($optedOut, InquirySlaReminder::class);
        $this->assertSame('triage', $inquiry->fresh()->sla_reminded_stage);

        // A second run does not nag again.
        Notification::fake();
        $this->artisan('sla:remind')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_command_reminds_only_the_owning_agencys_staff(): void
    {
        Notification::fake();

        $agency = Agency::factory()->create();
        $other = Agency::factory()->create();
        $owner = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $stranger = User::factory()->agencyStaff()->create(['agency_id' => $other->id]);
        $this->overdueInvestigation($agency);

        $this->artisan('sla:remind')->assertSuccessful();

        Notification::assertSentTo($owner, InquirySlaReminder::class);
        Notification::assertNotSentTo($stranger, InquirySlaReminder::class);
    }

    public function test_command_ignores_inquiries_that_are_on_time(): void
    {
        Notification::fake();

        User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::Submitted]);

        $this->artisan('sla:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'sla:remind'));

        $this->assertCount(1, $events);
        $this->assertSame('0 8 * * *', $events->first()->expression);
    }

    public function test_agency_and_mcmc_see_the_reminder_preference(): void
    {
        $mcmc = User::factory()->mcmcStaff()->create();
        Volt::actingAs($mcmc)->test('mcmc.settings.index')->set('tab', 'notifications')->assertSee('Overdue reminders');

        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        Volt::actingAs($staff)->test('profile.notification-preferences')->assertSee('Overdue reminders');
    }
}
