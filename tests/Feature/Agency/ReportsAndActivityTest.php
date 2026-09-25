<?php

namespace Tests\Feature\Agency;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsAndActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_only_reflect_the_staff_members_own_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue]);

        $summary = Volt::actingAs($staff)->test('agency.reports.index')->instance()->summary;

        $this->assertSame(1, $summary['verified']);
    }

    public function test_activity_log_only_shows_actions_on_the_staff_members_own_agency_inquiries(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        $mine = Inquiry::factory()->create(['agency_id' => $agencyA->id, 'title' => 'Mine']);
        $theirs = Inquiry::factory()->create(['agency_id' => $agencyB->id, 'title' => 'Theirs']);

        InquiryActivityLog::create(['inquiry_id' => $mine->id, 'user_id' => $staff->id, 'action' => 'investigation_updated', 'notes' => 'Progress on mine.']);
        InquiryActivityLog::create(['inquiry_id' => $theirs->id, 'user_id' => $staff->id, 'action' => 'investigation_updated', 'notes' => 'Progress on theirs.']);

        Volt::actingAs($staff)
            ->test('agency.activity.index')
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }
}
