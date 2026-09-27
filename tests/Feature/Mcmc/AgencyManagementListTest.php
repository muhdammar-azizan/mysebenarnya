<?php

namespace Tests\Feature\Mcmc;

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\AgencyAccountProvisioned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AgencyManagementListTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_the_agency_list_by_name(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create(['name' => 'Ministry of Health']);
        Agency::factory()->create(['name' => 'Bank Negara Malaysia']);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->set('search', 'Health')
            ->assertSee('Ministry of Health')
            ->assertDontSee('Bank Negara Malaysia');
    }

    public function test_status_filter_narrows_the_agency_list(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create(['name' => 'Active Agency', 'is_active' => true]);
        Agency::factory()->create(['name' => 'Suspended Agency', 'is_active' => false]);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->set('status', 'Suspended')
            ->assertSee('Suspended Agency')
            ->assertDontSee('Active Agency');
    }

    public function test_specialization_filter_narrows_the_agency_list(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        Agency::factory()->create(['name' => 'Health Agency', 'specialization' => InquiryCategory::HealthMedical]);
        Agency::factory()->create(['name' => 'Finance Agency', 'specialization' => InquiryCategory::FinancialScams]);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->set('specialization', InquiryCategory::HealthMedical->value)
            ->assertSee('Health Agency')
            ->assertDontSee('Finance Agency');
    }

    public function test_active_and_resolved_columns_show_real_workload_counts_not_staff_count(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        User::factory()->agencyStaff()->count(3)->create(['agency_id' => $agency->id]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::VerifiedTrue]);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::IdentifiedFake]);

        $component = Volt::actingAs($staff)->test('mcmc.agencies.index');

        $row = $component->instance()->agencies->firstWhere('id', $agency->id);

        $this->assertSame(1, $row->active_count);
        $this->assertSame(2, $row->resolved_count);
    }

    public function test_view_details_shows_agency_workload_breakdown(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Details Agency']);
        Inquiry::factory()->create(['agency_id' => $agency->id, 'status' => InquiryStatus::UnderInvestigation, 'jurisdiction_accepted_at' => null]);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->call('viewDetails', $agency->id)
            ->assertSee('Details Agency')
            ->assertSee('Pending');
    }

    public function test_suspending_an_agency_toggles_its_active_status(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['is_active' => true]);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->call('openSuspend', $agency->id)
            ->call('confirmSuspend');

        $this->assertFalse($agency->fresh()->is_active);
    }

    public function test_reactivating_a_suspended_agency_toggles_it_back_on(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['is_active' => false]);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->call('openSuspend', $agency->id)
            ->call('confirmSuspend');

        $this->assertTrue($agency->fresh()->is_active);
    }

    public function test_resend_credentials_generates_a_new_password_and_notifies_the_agency_admin(): void
    {
        Notification::fake();

        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create([
            'agency_id' => $agency->id,
            'agency_role' => AgencyStaffRole::Admin,
            'must_change_password' => false,
        ]);
        $oldPasswordHash = $admin->password;

        Volt::actingAs($staff)
            ->test('mcmc.agencies.index')
            ->call('resendCredentials', $agency->id);

        $admin->refresh();
        $this->assertNotSame($oldPasswordHash, $admin->password);
        $this->assertTrue($admin->must_change_password);

        Notification::assertSentTo($admin, AgencyAccountProvisioned::class);
    }

    public function test_public_user_cannot_access_agency_management(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.agencies.index'))->assertForbidden();
    }
}
