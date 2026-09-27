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

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accuracy_rate_is_computed_from_jurisdiction_decisions(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $accepted = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $accepted->id, 'user_id' => $staff->id, 'action' => 'jurisdiction_accepted']);

        $acceptedTwo = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $acceptedTwo->id, 'user_id' => $staff->id, 'action' => 'jurisdiction_accepted']);

        $rejected = Inquiry::factory()->create(['agency_id' => $agency->id]);
        InquiryActivityLog::create(['inquiry_id' => $rejected->id, 'user_id' => $staff->id, 'action' => 'jurisdiction_rejected']);

        $rate = Volt::actingAs($staff)->test('agency.reports.index')->get('accuracyRate');

        $this->assertSame(67, $rate);
    }

    public function test_accuracy_rate_is_null_when_no_jurisdiction_decisions_exist(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        Volt::actingAs($staff)
            ->test('agency.reports.index')
            ->assertSet('accuracyRate', null);
    }

    public function test_monthly_trend_only_counts_resolutions_within_the_last_six_months(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue, 'resolved_at' => now()->subMonths(8)]);

        $totalResolved = Volt::actingAs($staff)->test('agency.reports.index')->get('totalResolved');

        $this->assertSame(1, $totalResolved);
    }

    public function test_clicking_a_month_bar_shows_that_months_resolved_inquiries(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);

        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue,
            'title' => 'This months resolved case', 'resolved_at' => now(),
        ]);

        $component = Volt::actingAs($staff)->test('agency.reports.index');
        $key = now()->startOfMonth()->format('Y-m');

        $component->call('selectMonth', $key)
            ->assertSet('selectedMonth', $key)
            ->assertSee('This months resolved case');
    }

    public function test_clicking_the_same_month_bar_twice_closes_the_drilldown(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $key = now()->startOfMonth()->format('Y-m');

        Volt::actingAs($staff)
            ->test('agency.reports.index')
            ->call('selectMonth', $key)
            ->assertSet('selectedMonth', $key)
            ->call('selectMonth', $key)
            ->assertSet('selectedMonth', '');
    }

    public function test_category_breakdown_shows_bars_scoped_to_the_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agencyA->id]);

        Inquiry::factory()->create(['agency_id' => $agencyA->id, 'category' => \App\Enums\InquiryCategory::HealthMedical]);
        Inquiry::factory()->create(['agency_id' => $agencyB->id, 'category' => \App\Enums\InquiryCategory::FinancialScams]);

        Volt::actingAs($staff)
            ->test('agency.reports.index')
            ->assertSee('Health & Medical Claims')
            ->assertDontSee('Financial Scams & Banking');
    }
}
