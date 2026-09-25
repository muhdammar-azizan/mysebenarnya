<?php

namespace Tests\Feature\Public;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_phone_number(): void
    {
        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('profile.update-profile-information-form')
            ->set('phone', '+60 12-345 6789')
            ->call('updateProfileInformation');

        $this->assertSame('+60 12-345 6789', $user->fresh()->phone);
    }

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('profile.update-profile-photo-form')
            ->set('newPhoto', UploadedFile::fake()->image('avatar.png', 400, 400))
            ->call('save');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->profile_photo_path);
        Storage::disk('public')->assertExists($fresh->profile_photo_path);
    }

    public function test_removing_a_profile_photo_deletes_the_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => UserRole::Public]);

        Volt::actingAs($user)
            ->test('profile.update-profile-photo-form')
            ->set('newPhoto', UploadedFile::fake()->image('avatar.png'))
            ->call('save');

        $path = $user->fresh()->profile_photo_path;
        Storage::disk('public')->assertExists($path);

        Volt::actingAs($user->fresh())
            ->test('profile.update-profile-photo-form')
            ->call('removePhoto');

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->profile_photo_path);
    }
}
