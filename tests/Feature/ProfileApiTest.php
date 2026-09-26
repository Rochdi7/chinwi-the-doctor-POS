<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** /api/profil: the logged-in user changes their own name, password and photo. */
class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(User::AVATAR_DISK);
        $this->user = User::create(['name' => 'Mehdi', 'email' => 'mehdi@local.test', 'password' => 'old-secret-1']);
        $this->actingAs($this->user);
    }

    public function test_it_needs_a_login(): void
    {
        auth()->logout();

        $this->putJson('/api/profil', ['name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/profil/avatar')->assertUnauthorized();
    }

    public function test_the_name_changes_without_touching_the_password(): void
    {
        $this->putJson('/api/profil', ['name' => 'Mehdi B.'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Mehdi B.');

        $this->assertTrue(Hash::check('old-secret-1', $this->user->fresh()->password));
    }

    public function test_the_password_changes_with_the_current_one(): void
    {
        $this->putJson('/api/profil', [
            'name' => 'Mehdi', 'current_password' => 'old-secret-1', 'password' => 'new-secret-2', 'password_confirmation' => 'new-secret-2',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-secret-2', $this->user->fresh()->password));
    }

    public function test_a_wrong_current_password_or_confirmation_is_refused(): void
    {
        $this->putJson('/api/profil', [
            'name' => 'Mehdi', 'current_password' => 'nope', 'password' => 'new-secret-2', 'password_confirmation' => 'new-secret-2',
        ])->assertJsonValidationErrors('current_password');

        $this->putJson('/api/profil', [
            'name' => 'Mehdi', 'current_password' => 'old-secret-1', 'password' => 'new-secret-2', 'password_confirmation' => 'other',
        ])->assertJsonValidationErrors('password');

        $this->putJson('/api/profil', ['name' => 'Mehdi', 'password' => 'new-secret-2', 'password_confirmation' => 'new-secret-2'])
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('old-secret-1', $this->user->fresh()->password));
    }

    public function test_a_photo_is_stored_replaced_and_removed(): void
    {
        $first = $this->post('/api/profil/avatar', ['avatar' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
            ->assertOk()->json('user.avatar_url');
        $this->assertNotNull($first);
        $old = $this->user->fresh()->avatar;
        Storage::disk(User::AVATAR_DISK)->assertExists($old);

        $this->post('/api/profil/avatar', ['avatar' => UploadedFile::fake()->image('b.jpg')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk(User::AVATAR_DISK)->assertMissing($old);

        $current = $this->user->fresh()->avatar;
        $this->deleteJson('/api/profil/avatar')->assertOk()->assertJsonPath('user.avatar_url', null);
        Storage::disk(User::AVATAR_DISK)->assertMissing($current);
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->post('/api/profil/avatar', ['avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('avatar');
    }
}
