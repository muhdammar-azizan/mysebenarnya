<?php

namespace Tests\Feature\Public;

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SubmitInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_submit_an_inquiry(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('public.inquiries.create')
            ->set('title', 'Fake claim about free money')
            ->set('category', InquiryCategory::FinancialScams->value)
            ->set('description', 'This is a sufficiently long description of the claim being reported.')
            ->set('source_url', 'https://example.com/post')
            ->set('evidence', [UploadedFile::fake()->image('evidence.jpg')])
            ->call('submit')
            ->assertRedirect();

        $this->assertDatabaseHas('inquiries', [
            'submitted_by' => $user->id,
            'title' => 'Fake claim about free money',
            'status' => InquiryStatus::Submitted->value,
        ]);

        $inquiry = Inquiry::first();
        $this->assertNotNull($inquiry->reference_no);
        $this->assertCount(1, $inquiry->evidence);
        $this->assertCount(1, $inquiry->activityLogs);

        Storage::disk('public')->assertExists($inquiry->evidence->first()->file_path);
    }

    public function test_title_category_and_description_are_required(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('public.inquiries.create')
            ->set('title', '')
            ->set('category', '')
            ->set('description', '')
            ->call('submit')
            ->assertHasErrors(['title', 'category', 'description']);
    }

    public function test_description_must_be_at_least_20_characters(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('public.inquiries.create')
            ->set('title', 'Short')
            ->set('category', InquiryCategory::Other->value)
            ->set('description', 'too short')
            ->call('submit')
            ->assertHasErrors(['description']);
    }

    public function test_mcmc_staff_cannot_access_the_submit_route(): void
    {
        $staff = User::factory()->mcmcStaff()->create();

        $this->actingAs($staff)->get(route('inquiries.create'))->assertForbidden();
    }

    public function test_shows_a_retry_banner_when_submission_fails_unexpectedly(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        // An invalid category string passes Livewire's own 'required|string' rule
        // but fails Eloquent's enum cast when persisting, exercising the same
        // unexpected-failure path a real storage/DB error would take.
        Volt::actingAs($user)
            ->test('public.inquiries.create')
            ->set('title', 'Fake claim about free money')
            ->set('category', 'Not A Real Category')
            ->set('description', 'This is a sufficiently long description of the claim being reported.')
            ->call('submit')
            ->assertSee('Something went wrong while submitting your inquiry');

        $this->assertDatabaseCount('inquiries', 0);
    }
}
