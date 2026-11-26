<?php

namespace Tests\Feature;

use App\Enums\AgencyStaffRole;
use App\Enums\ClarificationPriority;
use App\Enums\ClarificationTopic;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\ClarificationThread;
use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Component;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Plays the part of an attacker: every role tries to reach pages and data that
 * belong to somebody else.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function publicUser(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => UserRole::Public]);
    }

    private function agencyUser(Agency $agency, AgencyStaffRole $role = AgencyStaffRole::Reviewer): User
    {
        return User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => $role]);
    }

    private function assignedInquiry(Agency $agency, ?User $submitter = null): Inquiry
    {
        return Inquiry::factory()->create([
            'submitted_by' => ($submitter ?? $this->publicUser())->id,
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);
    }

    // ---- Whole areas are closed to the wrong role ---------------------------------

    public function test_every_area_is_closed_to_the_wrong_role_and_to_guests(): void
    {
        $areas = [
            'public' => ['inquiries.create', 'inquiries.index', 'notifications.index'],
            'mcmc_staff' => ['mcmc.triage.index', 'mcmc.inquiries.index', 'mcmc.users.index', 'mcmc.agencies.index', 'mcmc.reports.index', 'mcmc.settings.index', 'mcmc.audit-log.index', 'mcmc.notifications.index'],
            'agency_staff' => ['agency.inquiries.index', 'agency.reports.index', 'agency.activity.index', 'agency.organization.index', 'agency.notifications.index'],
        ];

        $agency = Agency::factory()->create();
        $people = [
            'public' => $this->publicUser(),
            'mcmc_staff' => User::factory()->mcmcStaff()->create(),
            'agency_staff' => $this->agencyUser($agency),
        ];

        foreach ($areas as $owner => $routes) {
            foreach ($routes as $route) {
                auth()->logout();
                $this->get(route($route))->assertRedirect(route('login'));

                foreach ($people as $role => $person) {
                    $response = $this->actingAs($person)->get(route($route));

                    $role === $owner
                        ? $response->assertOk()
                        : $response->assertForbidden();
                }
            }
        }
    }

    // ---- Public users ------------------------------------------------------------

    public function test_a_public_user_cannot_open_another_users_inquiry_by_changing_the_url(): void
    {
        $aminah = $this->publicUser();
        $ali = $this->publicUser();
        $inquiry = Inquiry::factory()->create(['submitted_by' => $aminah->id]);

        $this->actingAs($aminah)->get(route('inquiries.show', $inquiry))->assertOk();
        $this->actingAs($ali)->get(route('inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_the_public_browse_page_hides_who_submitted_and_the_evidence_file_names(): void
    {
        $submitter = $this->publicUser(['name' => 'Aminah Rahsia', 'email' => 'aminah.rahsia@example.test']);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::VerifiedTrue]);
        InquiryEvidence::create([
            'inquiry_id' => $inquiry->id, 'uploaded_by' => $submitter->id, 'file_path' => 'evidence/x.pdf',
            'file_name' => 'Aminah-IC-Kuantan.pdf', 'file_type' => 'application/pdf', 'file_size' => 10,
        ]);

        $this->actingAs($this->publicUser())->get(route('browse.show', $inquiry))
            ->assertOk()
            ->assertDontSee('Aminah Rahsia')
            ->assertDontSee('aminah.rahsia@example.test')
            ->assertDontSee('Aminah-IC-Kuantan.pdf')
            ->assertSee('Evidence file 1');
    }

    public function test_discarded_inquiries_are_not_visible_in_browse(): void
    {
        $inquiry = Inquiry::factory()->create(['status' => InquiryStatus::Discarded]);

        $this->actingAs($this->publicUser())->get(route('browse.show', $inquiry))->assertForbidden();
    }

    public function test_a_new_account_is_always_a_public_user_without_an_agency(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'New Person')
            ->set('email', 'new.person@example.test')
            ->set('password', 'Password1!')
            ->set('password_confirmation', 'Password1!')
            ->set('terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'new.person@example.test')->firstOrFail();
        $this->assertSame(UserRole::Public, $user->role);
        $this->assertNull($user->agency_id);
    }

    public function test_marking_someone_elses_notification_as_read_does_nothing(): void
    {
        $inquiry = Inquiry::factory()->create();
        $victim = $this->publicUser();
        $attacker = $this->publicUser();

        $victim->notify(new InquiryStatusChanged($inquiry, InquiryStatus::Submitted->value, InquiryStatus::UnderInvestigation->value));
        $notification = $victim->notifications()->firstOrFail();

        Volt::actingAs($attacker)->test('public.notifications.index')->call('markRead', $notification->id);

        $this->assertNull($notification->fresh()->read_at);
    }

    // ---- Agencies ----------------------------------------------------------------

    public function test_an_agency_can_only_open_its_own_inquiries(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $inquiry = $this->assignedInquiry($agencyA);

        $this->actingAs($this->agencyUser($agencyA))->get(route('agency.inquiries.show', $inquiry))->assertOk();
        $this->actingAs($this->agencyUser($agencyB))->get(route('agency.inquiries.show', $inquiry))->assertForbidden();
    }

    public function test_an_unassigned_inquiry_is_closed_to_every_agency(): void
    {
        $inquiry = Inquiry::factory()->create(['agency_id' => null]);

        $this->actingAs($this->agencyUser(Agency::factory()->create()))
            ->get(route('agency.inquiries.show', $inquiry))
            ->assertForbidden();
    }

    public function test_an_agency_cannot_act_on_another_agencys_inquiry_through_the_page_itself(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $inquiry = $this->assignedInquiry($agencyA);

        Volt::actingAs($this->agencyUser($agencyB))
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->assertForbidden();
    }

    public function test_an_agency_cannot_reply_to_or_close_another_agencys_clarification_thread(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $staffA = $this->agencyUser($agencyA);
        $foreignThread = ClarificationThread::open(
            $this->assignedInquiry($agencyB),
            $this->agencyUser($agencyB),
            ClarificationTopic::Other,
            ClarificationPriority::Normal,
            'A private question from the other agency.'
        );

        $own = $this->assignedInquiry($agencyA);

        Volt::actingAs($staffA)
            ->test('agency.inquiries.show', ['inquiry' => $own])
            ->set('replyText', [$foreignThread->id => 'Let me barge into your conversation'])
            ->call('sendReply', $foreignThread->id)
            ->assertForbidden();

        Volt::actingAs($staffA)
            ->test('agency.inquiries.show', ['inquiry' => $own])
            ->set('closingThreadId', $foreignThread->id)
            ->call('confirmCloseThread')
            ->assertForbidden();

        $this->assertSame(1, $foreignThread->messages()->count());
    }

    public function test_a_consultation_is_only_open_to_the_consulted_agency(): void
    {
        $owner = Agency::factory()->create();
        $consulted = Agency::factory()->create();
        $bystander = Agency::factory()->create();
        $inquiry = $this->assignedInquiry($owner);
        $thread = ClarificationThread::open($inquiry, $this->agencyUser($owner), ClarificationTopic::Other, ClarificationPriority::Normal, 'Question for MCMC.');
        $consult = $thread->inviteConsult(User::factory()->mcmcStaff()->create(), $consulted, 'What do you think about this?');

        $this->actingAs($this->agencyUser($consulted))->get(route('agency.consultations.show', $consult))->assertOk();
        $this->actingAs($this->agencyUser($bystander))->get(route('agency.consultations.show', $consult))->assertForbidden();
        // Even the agency that owns the inquiry only sees the advice inside its own case.
        $this->actingAs($this->agencyUser($owner))->get(route('agency.consultations.show', $consult))->assertForbidden();
    }

    public function test_a_reviewer_cannot_use_admin_only_agency_actions(): void
    {
        $agency = Agency::factory()->create();
        $reviewer = $this->agencyUser($agency, AgencyStaffRole::Reviewer);

        Volt::actingAs($reviewer)->test('agency.organization.index')->call('openInviteModal')->assertForbidden();

        // A fresh page for the second attempt: a component cannot be reused after a 403.
        Volt::actingAs($reviewer)->test('agency.organization.index')
            ->set('inviteName', 'Intruder')->set('inviteEmail', 'intruder@example.test')->set('inviteRole', 'admin')
            ->call('sendInvite')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.test']);
    }

    public function test_an_agency_admin_only_manages_staff_of_their_own_agency(): void
    {
        $agencyA = Agency::factory()->create();
        $agencyB = Agency::factory()->create();
        $adminA = $this->agencyUser($agencyA, AgencyStaffRole::Admin);
        $staffB = $this->agencyUser($agencyB);

        Volt::actingAs($adminA)->test('agency.organization.index')
            ->assertDontSee($staffB->name)
            ->assertDontSee($staffB->email);
    }

    // ---- MCMC-only data ----------------------------------------------------------

    public function test_other_roles_cannot_use_mcmc_actions_even_by_calling_the_page_directly(): void
    {
        $agency = Agency::factory()->create();
        $inquiry = Inquiry::factory()->create();

        // The routes are closed by role (see the first test), so the pages cannot even be mounted.
        foreach ([$this->publicUser(), $this->agencyUser($agency)] as $outsider) {
            $this->actingAs($outsider)->get(route('mcmc.triage.index'))->assertForbidden();
            $this->actingAs($outsider)->get(route('mcmc.inquiries.show', $inquiry))->assertForbidden();
            $this->actingAs($outsider)->get(route('mcmc.agencies.edit', $agency))->assertForbidden();
            $this->actingAs($outsider)->get(route('mcmc.agencies.create'))->assertForbidden();
        }
    }
}
