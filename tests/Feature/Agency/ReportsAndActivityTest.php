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

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);

        $totalResolved = Volt::actingAs($staff)->test('agency.reports.index')->get('totalResolved');

        $this->assertSame(1, $totalResolved);
    }

    public function test_activity_log_only_shows_actions_taken_by_the_staff_members_own_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $myStaff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);
        $otherStaff = User::factory()->agencyStaff()->create(['agency_id' => $agencyB->id]);

        $mine = Inquiry::factory()->create(['agency_id' => $agencyA->id, 'title' => 'Mine']);
        $theirs = Inquiry::factory()->create(['agency_id' => $agencyB->id, 'title' => 'Theirs']);

        InquiryActivityLog::create(['inquiry_id' => $mine->id, 'user_id' => $myStaff->id, 'action' => 'investigation_updated', 'notes' => 'Progress on mine.']);
        InquiryActivityLog::create(['inquiry_id' => $theirs->id, 'user_id' => $otherStaff->id, 'action' => 'investigation_updated', 'notes' => 'Progress on theirs.']);

        Volt::actingAs($myStaff)
            ->test('agency.activity.index')
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }
}
