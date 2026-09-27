<?php

namespace Tests\Feature\Mcmc;

use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationPreferenceWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_mcmc_staff_are_notified_when_a_new_inquiry_is_submitted(): void
    {
        Notification::fake();

        $wantsIt = User::factory()->mcmcStaff()->create(['notification_preferences' => ['newInquiry' => true]]);
        $optedOut = User::factory()->mcmcStaff()->create(['notification_preferences' => ['newInquiry' => false]]);
        $submitter = User::factory()->create();

        Volt::actingAs($submitter)
            ->test('public.inquiries.create')
            ->set('title', 'A claim to verify')
            ->set('category', \App\Enums\InquiryCategory::HealthMedical->value)
            ->set('description', 'A description that is definitely long enough.')
            ->call('submit');

        Notification::assertSentTo($wantsIt, \App\Notifications\NewInquirySubmitted::class);
        Notification::assertNotSentTo($optedOut, \App\Notifications\NewInquirySubmitted::class);
    }

    public function test_other_mcmc_staff_are_notified_when_a_new_agency_is_registered(): void
    {
        Notification::fake();

        $actor = User::factory()->mcmcStaff()->create();
        $wantsIt = User::factory()->mcmcStaff()->create(['notification_preferences' => ['newAgency' => true]]);
        $optedOut = User::factory()->mcmcStaff()->create(['notification_preferences' => ['newAgency' => false]]);

        Volt::actingAs($actor)
            ->test('mcmc.agencies.create')
            ->set('name', 'Ministry of Test')
            ->set('specialization', \App\Enums\InquiryCategory::HealthMedical->value)
            ->set('contactName', 'Jane Doe')
            ->set('contactEmail', 'jane@example.gov.my')
            ->set('contactPhone', '03-1234 5678')
            ->call('register');

        Notification::assertSentTo($wantsIt, \App\Notifications\NewAgencyRegistered::class);
        Notification::assertNotSentTo($optedOut, \App\Notifications\NewAgencyRegistered::class);
        Notification::assertNothingSentTo($actor);
    }

    public function test_reviewer_is_not_notified_of_a_rejection_when_opted_out(): void
    {
        Notification::fake();

        $agency = Agency::factory()->create();
        $agencyStaff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id]);
        $reviewer = User::factory()->mcmcStaff()->create(['notification_preferences' => ['agencyRejects' => false]]);
        $inquiry = Inquiry::factory()->create([
            'status' => InquiryStatus::UnderInvestigation,
            'agency_id' => $agency->id,
            'reviewed_by' => $reviewer->id,
            'jurisdiction_accepted_at' => null,
        ]);

        Volt::actingAs($agencyStaff)
            ->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('rejectReason', 'Outside our jurisdiction, please reassign.')
            ->call('confirmReject');

        Notification::assertNotSentTo($reviewer, \App\Notifications\InquiryStatusChanged::class);
    }
}
