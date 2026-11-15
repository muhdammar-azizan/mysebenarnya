<?php

namespace Tests\Feature;

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_and_faq_pages_are_visible_to_guests(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('About MySebenarnya');
        $this->get(route('faq'))->assertOk()->assertSee('Frequently Asked Questions')->assertSee('How to spot fake news');
    }

    public function test_landing_page_links_to_the_faq(): void
    {
        $this->get('/')->assertOk()->assertSee(route('faq'), false);
    }

    public function test_public_sidebar_links_to_help_and_faq(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Help &amp; FAQ', false);
    }

    public function test_forbidden_page_uses_the_branded_403_view(): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($user)->get(route('mcmc.reports.index'))
            ->assertForbidden()
            ->assertSee('Access Denied');
    }

    public function test_branded_500_and_503_views_render(): void
    {
        $this->assertStringContainsString('Something Went Wrong', view('errors.500')->render());
        $this->assertStringContainsString('Under Maintenance', view('errors.503')->render());
        $this->assertStringContainsString('Too Many Requests', view('errors.429')->render());
    }

    public function test_share_buttons_show_only_for_published_results(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public]);

        $verified = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::VerifiedTrue]);
        $pending = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::UnderInvestigation]);

        $this->actingAs($viewer)->get(route('browse.show', $verified))
            ->assertOk()
            ->assertSee('Share this result')
            ->assertSee('wa.me');

        $this->actingAs($viewer)->get(route('browse.show', $pending))
            ->assertOk()
            ->assertDontSee('Share this result');
    }

    public function test_share_text_does_not_reveal_the_submitter(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Public]);
        $submitter = User::factory()->create(['role' => UserRole::Public, 'name' => 'Secret Submitter Name']);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $submitter->id, 'status' => InquiryStatus::IdentifiedFake]);

        $this->actingAs($viewer)->get(route('browse.show', $inquiry))
            ->assertOk()
            ->assertDontSee('Secret Submitter Name')
            ->assertDontSee(rawurlencode('Secret Submitter Name'));
    }
}
