<?php

namespace Tests\Feature\Agency;

use App\Enums\AgencyStaffRole;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\AgencyStaffInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_agency_contact_details(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->set('contactEmail', 'newcontact@agency.gov.my')
            ->set('contactPhone', '03-9999 0000')
            ->call('saveInfo')
            ->assertHasNoErrors();

        $this->assertSame('newcontact@agency.gov.my', $agency->fresh()->contact_email);
    }

    public function test_admin_can_update_agency_name(): void
    {
        $agency = Agency::factory()->create(['name' => 'Old Agency Name']);
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->set('agencyName', 'New Agency Name')
            ->set('contactEmail', $agency->contact_email ?? 'contact@agency.gov.my')
            ->set('contactPhone', $agency->contact_phone ?? '03-1111 2222')
            ->call('saveInfo')
            ->assertHasNoErrors();

        $this->assertSame('New Agency Name', $agency->fresh()->name);
    }

    public function test_reviewer_cannot_update_agency_name(): void
    {
        $agency = Agency::factory()->create(['name' => 'Original Name']);
        $reviewer = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);

        Volt::actingAs($reviewer)
            ->test('agency.organization.index')
            ->set('agencyName', 'Hacked Name')
            ->call('saveInfo')
            ->assertForbidden();

        $this->assertSame('Original Name', $agency->fresh()->name);
    }

    public function test_admin_can_upload_agency_logo(): void
    {
        Storage::fake('public');

        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->set('newLogo', UploadedFile::fake()->image('logo.png'))
            ->call('saveLogo')
            ->assertHasNoErrors();

        $agency->refresh();
        $this->assertNotNull($agency->logo_path);
        Storage::disk('public')->assertExists($agency->logo_path);
    }

    public function test_admin_can_remove_agency_logo(): void
    {
        Storage::fake('public');

        $agency = Agency::factory()->create(['logo_path' => 'agency-logos/existing.png']);
        Storage::disk('public')->put('agency-logos/existing.png', 'fake-content');
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->call('removeLogo');

        $this->assertNull($agency->fresh()->logo_path);
        Storage::disk('public')->assertMissing('agency-logos/existing.png');
    }

    public function test_reviewer_cannot_upload_agency_logo(): void
    {
        Storage::fake('public');

        $agency = Agency::factory()->create();
        $reviewer = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);

        Volt::actingAs($reviewer)
            ->test('agency.organization.index')
            ->set('newLogo', UploadedFile::fake()->image('logo.png'))
            ->call('saveLogo')
            ->assertForbidden();

        $this->assertNull($agency->fresh()->logo_path);
    }

    public function test_reviewer_cannot_update_agency_contact_details(): void
    {
        $agency = Agency::factory()->create(['contact_email' => 'original@agency.gov.my']);
        $reviewer = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);

        Volt::actingAs($reviewer)
            ->test('agency.organization.index')
            ->set('contactEmail', 'hacked@agency.gov.my')
            ->call('saveInfo')
            ->assertForbidden();

        $this->assertSame('original@agency.gov.my', $agency->fresh()->contact_email);
    }

    public function test_admin_can_invite_a_new_staff_member(): void
    {
        Notification::fake();

        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->set('inviteName', 'New Reviewer')
            ->set('inviteEmail', 'reviewer@agency.gov.my')
            ->set('inviteRole', 'reviewer')
            ->call('sendInvite')
            ->assertHasNoErrors();

        $newStaff = User::where('email', 'reviewer@agency.gov.my')->first();
        $this->assertNotNull($newStaff);
        $this->assertSame($agency->id, $newStaff->agency_id);
        $this->assertSame(AgencyStaffRole::Reviewer, $newStaff->agency_role);
        $this->assertTrue($newStaff->must_change_password);

        Notification::assertSentTo($newStaff, AgencyStaffInvited::class);
    }

    public function test_reviewer_cannot_invite_staff(): void
    {
        $agency = Agency::factory()->create();
        $reviewer = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);

        Volt::actingAs($reviewer)
            ->test('agency.organization.index')
            ->set('inviteName', 'Sneaky')
            ->set('inviteEmail', 'sneaky@agency.gov.my')
            ->call('sendInvite')
            ->assertForbidden();

        $this->assertNull(User::where('email', 'sneaky@agency.gov.my')->first());
    }

    public function test_jurisdiction_is_shown_but_not_editable(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->assertSee($agency->specialization->value)
            ->assertSee('Locked');
    }
}
