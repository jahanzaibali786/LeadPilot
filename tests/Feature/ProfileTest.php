<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_shows_welcome_copy(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Sign in to your lead workspace.');
    }

    public function test_authenticated_user_can_open_and_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Old Name')
            ->assertSee(date('Y').' LeadPilot. All rights reserved.');

        $this->put(route('profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->actingAs($user)->from(route('profile.show'))->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('profile.show'))->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
    }


    public function test_public_storage_files_are_served_without_symlink(): void
    {
        Storage::disk('public')->put('profile-photos/avatar.png', 'fake-image-content');

        $this->get('/storage/profile-photos/avatar.png')
            ->assertOk();
    }

    public function test_public_storage_route_blocks_path_traversal(): void
    {
        $this->get('/storage/../.env')->assertNotFound();
    }

    public function test_user_can_upload_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('profile.jpg', 300, 300),
        ])->assertRedirect();

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->get(route('profile.show'))->assertSee('storage/'.$path, false);
    }}