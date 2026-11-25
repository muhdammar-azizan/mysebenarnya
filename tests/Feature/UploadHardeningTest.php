<?php

namespace Tests\Feature;

use App\Enums\AgencyStaffRole;
use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use App\Support\ImageSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UploadHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'SECRET-GPS-MARKER';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    /** Build a real JPEG and hide a marker in an EXIF-style APP1 segment, like a phone photo's GPS data. */
    private function jpegWithExif(string $name = 'photo.jpg', int $width = 1200, int $height = 900): UploadedFile
    {
        $canvas = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($canvas);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0".self::SECRET;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        $withExif = substr($jpeg, 0, 2).$segment.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent($name, $withExif);
    }

    private function submitData(Volt\Testable|\Livewire\Features\SupportTesting\Testable $component, array $files)
    {
        return $component
            ->set('title', 'Fake claim about free money')
            ->set('category', InquiryCategory::FinancialScams->value)
            ->set('description', 'This is a sufficiently long description of the claim being reported.')
            ->set('evidence', $files)
            ->call('submit');
    }

    public function test_the_sanitizer_removes_exif_data_and_limits_the_size(): void
    {
        $file = $this->jpegWithExif('photo.jpg', 2000, 1000);
        $this->assertStringContainsString(self::SECRET, file_get_contents($file->getRealPath()));

        ['data' => $data] = app(ImageSanitizer::class)->clean($file->getRealPath(), 800);

        $this->assertStringNotContainsString(self::SECRET, $data);
        $this->assertStringNotContainsString('Exif', $data);

        $size = getimagesizefromstring($data);
        $this->assertSame(800, $size[0]);
        $this->assertSame(400, $size[1]);
    }

    public function test_the_sanitizer_rejects_files_that_are_not_images(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'not an image');

        $this->expectException(\InvalidArgumentException::class);

        app(ImageSanitizer::class)->clean($path);
    }

    public function test_images_with_huge_dimensions_are_rejected_even_when_the_file_is_small(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $component = $this->submitData(
            Volt::actingAs($user)->test('public.inquiries.create'),
            [UploadedFile::fake()->image('bomb.png', 7000, 10)]
        );

        $component->assertHasErrors('evidence.0');
        $this->assertSame(0, Inquiry::count());
    }

    public function test_more_than_the_allowed_number_of_files_is_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $files = array_map(fn ($i) => UploadedFile::fake()->image("e{$i}.jpg", 50, 50), range(1, 6));

        $this->submitData(Volt::actingAs($user)->test('public.inquiries.create'), $files)->assertHasErrors('evidence');

        $this->assertSame(0, Inquiry::count());
    }

    public function test_the_daily_upload_quota_is_enforced(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);
        $inquiry = Inquiry::factory()->create(['submitted_by' => $user->id]);

        for ($i = 0; $i < config('uploads.daily_file_quota'); $i++) {
            InquiryEvidence::create(['inquiry_id' => $inquiry->id, 'uploaded_by' => $user->id, 'file_path' => "evidence/{$i}.pdf", 'file_name' => "{$i}.pdf", 'file_type' => 'application/pdf', 'file_size' => 1]);
        }

        $this->submitData(Volt::actingAs($user)->test('public.inquiries.create'), [UploadedFile::fake()->image('e.jpg', 50, 50)])
            ->assertHasErrors('evidence');

        $this->assertSame(1, Inquiry::count());
    }

    public function test_a_real_account_keeps_the_original_file_and_name(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        $this->submitData(Volt::actingAs($user)->test('public.inquiries.create'), [$this->jpegWithExif('my-photo.jpg')])->assertHasNoErrors();

        $evidence = InquiryEvidence::firstOrFail();
        $this->assertSame('my-photo.jpg', $evidence->file_name);
        $this->assertStringContainsString(self::SECRET, Storage::disk('local')->get($evidence->file_path));
    }

    public function test_a_demo_account_gets_a_clean_image_and_a_generic_english_name(): void
    {
        $user = User::factory()->demo()->create(['role' => UserRole::Public]);

        $this->submitData(Volt::actingAs($user)->test('public.inquiries.create'), [$this->jpegWithExif('Aminah-rumah-kuantan.jpg')])->assertHasNoErrors();

        $evidence = InquiryEvidence::firstOrFail();
        $this->assertSame('evidence-1.jpg', $evidence->file_name);
        $this->assertSame('image/jpeg', $evidence->file_type);
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertStringNotContainsString(self::SECRET, Storage::disk('local')->get($evidence->file_path));
        $this->assertStringNotContainsString('Aminah', $evidence->file_name);
    }

    public function test_a_demo_pdf_is_stored_under_a_generic_name(): void
    {
        $user = User::factory()->demo()->create(['role' => UserRole::Public]);

        $this->submitData(Volt::actingAs($user)->test('public.inquiries.create'), [UploadedFile::fake()->create('Aminah-IC.pdf', 20, 'application/pdf')])->assertHasNoErrors();

        $this->assertSame('evidence-1.pdf', InquiryEvidence::firstOrFail()->file_name);
    }

    public function test_the_demo_notice_is_only_shown_to_demo_accounts(): void
    {
        $demo = User::factory()->demo()->create(['role' => UserRole::Public]);
        $real = User::factory()->create(['role' => UserRole::Public]);

        $this->actingAs($demo)->get(route('inquiries.create'))->assertOk()->assertSee('This is a demo system.');
        $this->actingAs($real)->get(route('inquiries.create'))->assertOk()->assertDontSee('This is a demo system.');
    }

    public function test_the_demo_notice_is_shown_in_malay(): void
    {
        $demo = User::factory()->demo()->create(['role' => UserRole::Public, 'locale' => 'ms']);

        $this->actingAs($demo)->get(route('inquiries.create'))->assertOk()->assertSee('Ini ialah sistem demo.');
    }

    public function test_agency_uploads_are_validated_limited_and_sanitised(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);

        $component = Volt::actingAs($staff)->test('agency.inquiries.show', ['inquiry' => $inquiry]);

        // A text file disguised as evidence is refused.
        $component->set('newEvidence', [UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])
            ->call('saveDraft')
            ->assertHasErrors('newEvidence.0');

        // Too many files at once is refused.
        $component->set('newEvidence', array_map(fn ($i) => UploadedFile::fake()->image("e{$i}.jpg", 50, 50), range(1, 6)))
            ->call('saveDraft')
            ->assertHasErrors('newEvidence');

        $this->assertSame(0, $inquiry->evidence()->count());
    }

    public function test_a_demo_agency_upload_is_clean_and_named_generically(): void
    {
        $agency = Agency::factory()->create();
        $staff = User::factory()->demo()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Reviewer]);
        $inquiry = Inquiry::factory()->create([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => now(),
        ]);

        Volt::actingAs($staff)->test('agency.inquiries.show', ['inquiry' => $inquiry])
            ->set('newEvidence', [$this->jpegWithExif('private-name.jpg')])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence = $inquiry->evidence()->firstOrFail();
        $this->assertSame('evidence-1.jpg', $evidence->file_name);
        $this->assertStringNotContainsString(self::SECRET, Storage::disk('local')->get($evidence->file_path));
    }

    public function test_profile_photos_are_reencoded_without_exif_and_resized(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('profile.update-profile-photo-form')
            ->set('newPhoto', $this->jpegWithExif('selfie.jpg', 1600, 1200))
            ->call('save')
            ->assertHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertLessThanOrEqual(800, getimagesizefromstring($stored)[0]);
    }

    public function test_oversized_profile_photos_are_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('profile.update-profile-photo-form')
            ->set('newPhoto', UploadedFile::fake()->image('bomb.png', 7000, 10))
            ->call('save')
            ->assertHasErrors('newPhoto');

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_agency_logos_are_reencoded_and_resized(): void
    {
        $agency = Agency::factory()->create();
        $admin = User::factory()->agencyStaff()->create(['agency_id' => $agency->id, 'agency_role' => AgencyStaffRole::Admin]);

        Volt::actingAs($admin)
            ->test('agency.organization.index')
            ->set('newLogo', $this->jpegWithExif('logo.jpg', 1500, 1500))
            ->call('saveLogo')
            ->assertHasNoErrors();

        $stored = Storage::disk('public')->get($agency->fresh()->logo_path);

        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertLessThanOrEqual(512, getimagesizefromstring($stored)[0]);
    }
}
