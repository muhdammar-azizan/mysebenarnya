<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Inquiry $inquiry;

    private InquiryEvidence $evidence;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->agency = Agency::factory()->create();
        $this->owner = User::factory()->create(['role' => UserRole::Public]);
        $this->inquiry = Inquiry::factory()->create([
            'submitted_by' => $this->owner->id,
            'agency_id' => $this->agency->id,
        ]);

        Storage::disk('local')->put('evidence/proof.pdf', '%PDF-1.4 test');

        $this->evidence = InquiryEvidence::create([
            'inquiry_id' => $this->inquiry->id,
            'uploaded_by' => $this->owner->id,
            'file_path' => 'evidence/proof.pdf',
            'file_name' => 'proof.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 13,
        ]);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('evidence.show', $this->evidence))->assertRedirect(route('login'));
    }

    public function test_the_submitter_can_open_their_evidence_with_safe_headers(): void
    {
        $response = $this->actingAs($this->owner)->get(route('evidence.show', $this->evidence));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_mcmc_staff_can_open_any_evidence(): void
    {
        $staff = User::factory()->create(['role' => UserRole::McmcStaff]);

        $this->actingAs($staff)->get(route('evidence.show', $this->evidence))->assertOk();
    }

    public function test_staff_of_the_assigned_agency_can_open_evidence(): void
    {
        $staff = User::factory()->create(['role' => UserRole::AgencyStaff, 'agency_id' => $this->agency->id]);

        $this->actingAs($staff)->get(route('evidence.show', $this->evidence))->assertOk();
    }

    public function test_staff_of_another_agency_cannot_open_evidence(): void
    {
        $other = Agency::factory()->create();
        $staff = User::factory()->create(['role' => UserRole::AgencyStaff, 'agency_id' => $other->id]);

        $this->actingAs($staff)->get(route('evidence.show', $this->evidence))->assertForbidden();
    }

    public function test_another_public_user_cannot_open_evidence(): void
    {
        $stranger = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($stranger)->get(route('evidence.show', $this->evidence))->assertForbidden();
    }

    public function test_a_missing_file_returns_404(): void
    {
        Storage::disk('local')->delete('evidence/proof.pdf');

        $this->actingAs($this->owner)->get(route('evidence.show', $this->evidence))->assertNotFound();
    }

    public function test_unknown_file_types_are_downloaded_not_rendered(): void
    {
        Storage::disk('local')->put('evidence/page.html', '<script>alert(1)</script>');
        $evidence = InquiryEvidence::create([
            'inquiry_id' => $this->inquiry->id,
            'uploaded_by' => $this->owner->id,
            'file_path' => 'evidence/page.html',
            'file_name' => 'page.html',
            'file_type' => 'application/pdf',
            'file_size' => 20,
        ]);

        $response = $this->actingAs($this->owner)->get(route('evidence.show', $evidence));

        $response->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_files_still_on_the_public_disk_remain_readable_until_moved(): void
    {
        Storage::disk('local')->delete('evidence/proof.pdf');
        Storage::disk('public')->put('evidence/proof.pdf', '%PDF-1.4 legacy');

        $this->actingAs($this->owner)->get(route('evidence.show', $this->evidence))->assertOk();
    }

    public function test_the_move_command_makes_public_files_private_and_is_repeatable(): void
    {
        Storage::disk('local')->delete('evidence/proof.pdf');
        Storage::disk('public')->put('evidence/proof.pdf', '%PDF-1.4 legacy');

        $this->artisan('evidence:make-private')->expectsOutputToContain('Moved 1 file(s)')->assertSuccessful();

        Storage::disk('public')->assertMissing('evidence/proof.pdf');
        Storage::disk('local')->assertExists('evidence/proof.pdf');
        $this->assertSame('%PDF-1.4 legacy', Storage::disk('local')->get('evidence/proof.pdf'));

        $this->artisan('evidence:make-private')->expectsOutputToContain('Moved 0 file(s)')->assertSuccessful();
    }
}
