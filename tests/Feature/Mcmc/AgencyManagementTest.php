<?php

namespace Tests\Feature\Mcmc;

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryCategory;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\AgencyAccountProvisioned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AgencyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_an_agency_creates_the_agency_and_its_first_admin_user(): void
    {
        Notification::fake();

        $staff = User::factory()->mcmcStaff()->create();

        Volt::actingAs($staff)
            ->test('mcmc.agencies.create')
            ->set('name', 'Ministry of Testing')
            ->set('specialization', InquiryCategory::HealthMedical->value)
            ->set('contactName', 'Dr. Test Person')
            ->set('contactEmail', 'admin@testing.gov.my')
            ->set('contactPhone', '03-1234 5678')
            ->call('register')
            ->assertHasNoErrors();

        $agency = Agency::where('name', 'Ministry of Testing')->first();
        $this->assertNotNull($agency);
        $this->assertNotEmpty($agency->code);

        $admin = User::where('email', 'admin@testing.gov.my')->first();
        $this->assertNotNull($admin);
        $this->assertSame(UserRole::AgencyStaff, $admin->role);
        $this->assertSame(AgencyStaffRole::Admin, $admin->agency_role);
        $this->assertSame($agency->id, $admin->agency_id);
        $this->assertTrue($admin->must_change_password);

        Notification::assertSentTo($admin, AgencyAccountProvisioned::class);
    }

    public function test_duplicate_contact_email_is_rejected(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        User::factory()->create(['email' => 'taken@testing.gov.my']);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.create')
            ->set('name', 'Another Agency')
            ->set('specialization', InquiryCategory::FinancialScams->value)
            ->set('contactName', 'Someone')
            ->set('contactEmail', 'taken@testing.gov.my')
            ->set('contactPhone', '03-1111 2222')
            ->call('register')
            ->assertHasErrors(['contactEmail']);
    }

    public function test_editing_an_agency_updates_its_fields(): void
    {
        $staff = User::factory()->mcmcStaff()->create();
        $agency = Agency::factory()->create(['name' => 'Old Name']);

        Volt::actingAs($staff)
            ->test('mcmc.agencies.edit', ['agency' => $agency])
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $agency->fresh()->name);
    }

    public function test_public_user_cannot_register_an_agency(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.agencies.create'))->assertForbidden();
    }
}
