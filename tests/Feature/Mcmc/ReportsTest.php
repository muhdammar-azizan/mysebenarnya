<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiry_overview_tab_shows_status_breakdown(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Inquiry::factory()->create(['status' => InquiryStatus::VerifiedTrue]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->assertSee('Verified True');
    }

    public function test_agency_performance_tab_shows_resolution_rate(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Test Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'agencies')
            ->assertSee('Test Agency')
            ->assertSee('50%');
    }

    public function test_user_growth_tab_shows_registration_stats(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.reports.index')
            ->set('tab', 'users')
            ->assertSee('Total Public Users');
    }
}
