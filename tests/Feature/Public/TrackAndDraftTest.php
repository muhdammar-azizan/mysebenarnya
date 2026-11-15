<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TrackAndDraftTest extends TestCase
{
    use RefreshDatabase;

    private function publicUser(): User
    {
        return User::factory()->create(['role' => UserRole::Public]);
    }

    // ---------- Track by reference number ----------

    public function test_guest_can_open_the_track_page(): void
    {
        $this->get(route('track'))->assertOk()->assertSee('Track an Inquiry');
    }

    public function test_a_valid_reference_shows_status_without_private_details(): void
    {
        $submitter = User::factory()->create(['role' => UserRole::Public, 'name' => 'Hidden Submitter']);
        $inquiry = Inquiry::factory()->create([
            'submitted_by' => $submitter->id,
            'title' => 'A very private claim title',
            'description' => 'Secret description text for this inquiry.',
            'status' => InquiryStatus::UnderInvestigation,
        ]);

        $this->get(route('track', ['ref' => strtolower($inquiry->reference_no)]))
            ->assertOk()
            ->assertSee($inquiry->reference_no)
            ->assertSee('Under Investigation')
            ->assertDontSee('A very private claim title')
            ->assertDontSee('Secret description text')
            ->assertDontSee('Hidden Submitter');
    }

    public function test_an_unknown_reference_shows_a_not_found_message(): void
    {
        $this->get(route('track', ['ref' => 'INQ-2026-99999']))
            ->assertOk()
            ->assertSee('No inquiry was found');
    }

    public function test_a_badly_formatted_reference_is_rejected(): void
    {
        $this->get(route('track', ['ref' => 'hello']))
            ->assertOk()
            ->assertSee('valid reference number');
    }

    public function test_track_lookups_are_rate_limited(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('track', ['ref' => 'INQ-2026-00001']))->assertOk();
        }

        $this->get(route('track', ['ref' => 'INQ-2026-00001']))->assertStatus(429);
    }

    // ---------- Drafts ----------

    public function test_a_user_can_save_a_draft_with_partial_data(): void
    {
        $user = $this->publicUser();

        Volt::actingAs($user)->test('public.inquiries.create')
            ->set('title', 'Half written claim')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inquiry_drafts', ['user_id' => $user->id, 'title' => 'Half written claim']);
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_saving_an_empty_draft_is_refused(): void
    {
        $user = $this->publicUser();

        Volt::actingAs($user)->test('public.inquiries.create')
            ->call('saveDraft')
            ->assertHasErrors('title');

        $this->assertDatabaseCount('inquiry_drafts', 0);
    }

    public function test_saving_again_updates_the_same_draft(): void
    {
        $user = $this->publicUser();

        $component = Volt::actingAs($user)->test('public.inquiries.create')
            ->set('title', 'First version')
            ->call('saveDraft')
            ->set('title', 'Second version')
            ->call('saveDraft');

        $component->assertHasNoErrors();
        $this->assertDatabaseCount('inquiry_drafts', 1);
        $this->assertDatabaseHas('inquiry_drafts', ['user_id' => $user->id, 'title' => 'Second version']);
    }

    public function test_an_existing_draft_is_loaded_into_the_form(): void
    {
        $user = $this->publicUser();
        InquiryDraft::create(['user_id' => $user->id, 'title' => 'Saved earlier', 'description' => 'Some saved description text here.']);

        Volt::actingAs($user)->test('public.inquiries.create')
            ->assertSet('title', 'Saved earlier')
            ->assertSet('description', 'Some saved description text here.')
            ->assertSet('hasDraft', true);
    }

    public function test_another_users_draft_is_not_loaded(): void
    {
        $owner = $this->publicUser();
        InquiryDraft::create(['user_id' => $owner->id, 'title' => 'Owner draft']);

        Volt::actingAs($this->publicUser())->test('public.inquiries.create')
            ->assertSet('title', '')
            ->assertSet('hasDraft', false);
    }

    public function test_submitting_the_inquiry_removes_the_draft(): void
    {
        $user = $this->publicUser();
        InquiryDraft::create(['user_id' => $user->id, 'title' => 'Draft to finish']);

        Volt::actingAs($user)->test('public.inquiries.create')
            ->set('title', 'Final claim title')
            ->set('category', \App\Enums\InquiryCategory::cases()[0]->value)
            ->set('description', 'A long enough description for validation.')
            ->set('source_url', 'https://example.com/post')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('inquiry_drafts', 0);
        $this->assertDatabaseCount('inquiries', 1);
    }

    public function test_discarding_a_draft_deletes_it(): void
    {
        $user = $this->publicUser();
        InquiryDraft::create(['user_id' => $user->id, 'title' => 'Throw away']);

        Volt::actingAs($user)->test('public.inquiries.create')
            ->call('discardDraft')
            ->assertSet('title', '')
            ->assertSet('hasDraft', false);

        $this->assertDatabaseCount('inquiry_drafts', 0);
    }

    public function test_my_inquiries_shows_a_continue_draft_banner(): void
    {
        $user = $this->publicUser();
        InquiryDraft::create(['user_id' => $user->id, 'title' => 'Unfinished']);

        $this->actingAs($user)->get(route('inquiries.index'))
            ->assertOk()
            ->assertSee('Continue draft');
    }
}
