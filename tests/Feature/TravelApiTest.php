<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\Trip;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_trip_and_movement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tripResponse = $this->postJson('/api/v1/trips', [
            'title' => 'Siem Reap',
            'description' => 'Temple visit',
            'category' => 'Culture',
        ]);

        $tripResponse
            ->assertCreated()
            ->assertJsonPath('data.trip.title', 'Siem Reap')
            ->assertJsonPath('data.trip.category', 'Culture');

        $tripId = $tripResponse->json('data.trip.id');

        $movementResponse = $this->postJson('/api/v1/movements', [
            'trip_id' => $tripId,
            'title' => 'Sunrise',
            'note' => 'Early morning at Angkor Wat',
            'place' => 'Angkor Wat',
            'dateTime' => '2026-06-20T06:00:00+07:00',
        ]);

        $movementResponse
            ->assertCreated()
            ->assertJsonPath('data.memory.title', 'Sunrise')
            ->assertJsonPath('data.memory.place', 'Angkor Wat');

        $this->getJson('/api/v1/movements?trip_id='.$tripId)
            ->assertOk()
            ->assertJsonPath('data.trip_id', $tripId)
            ->assertJsonCount(1, 'data.movements');
    }

    public function test_user_can_sign_in_with_a_verified_google_id_token(): void
    {
        $verifier = \Mockery::mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('google-id-token')
            ->andReturn([
                'sub' => 'google-user-123',
                'email' => 'traveler@example.com',
                'email_verified' => true,
                'name' => 'Google Traveler',
            ]);
        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'google-id-token',
            'device_name' => 'Postman',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'traveler@example.com')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'traveler@example.com',
            'google_id' => 'google-user-123',
        ]);
    }

    public function test_register_rejects_weak_or_mismatched_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Weak Password',
            'email' => 'weak-password@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password')
            ->assertJsonFragment(['Password confirmation does not match.'])
            ->assertJsonFragment(['Password must contain uppercase and lowercase letters.'])
            ->assertJsonFragment(['Password must contain at least one number.'])
            ->assertJsonFragment(['Password must contain at least one special character.']);
    }

    public function test_forgot_password_stores_hashed_reset_code_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'traveler@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => strtoupper($user->email),
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->created_at);
        $this->assertFalse(preg_match('/^\d{6}$/', $record->token) === 1);
    }

    public function test_forgot_password_returns_success_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_verify_reset_code_checks_hash_and_expiry(): void
    {
        $user = User::factory()->create([
            'email' => 'traveler@example.com',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subMinutes(16),
        ]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => $user->email,
            'code' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('code');
    }

    public function test_reset_password_updates_password_and_deletes_reset_token(): void
    {
        $user = User::factory()->create([
            'email' => 'traveler@example.com',
            'password' => 'oldpassword123',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertUnauthorized();
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'oldpassword123',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Current password is incorrect.');

        $this->assertTrue(Hash::check('oldpassword123', $user->fresh()->password));
    }

    public function test_change_password_updates_password_and_keeps_forgot_reset_flow_separate(): void
    {
        $user = User::factory()->create([
            'email' => 'traveler@example.com',
            'password' => 'oldpassword123',
        ]);
        $currentToken = $user->createToken('current-token')->accessToken;
        $oldToken = $user->createToken('old-token')->accessToken;

        $this->actingAs($user->withAccessToken($currentToken), 'sanctum');

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $oldToken->id,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_global_movement_store_requires_trip_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/movements', [
            'title' => 'Missing trip',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trip_id');
    }

    public function test_trip_movement_store_uses_route_trip_id_and_accepts_android_multipart_fields(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $routeTrip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Route trip',
        ]);
        $requestTrip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Request trip',
        ]);

        $response = $this->post('/api/v1/trips/'.$routeTrip->id.'/movements', [
            'trip_id' => $requestTrip->id,
            'title' => 'Android upload',
            'note' => 'Multipart note',
            'place' => 'Bangkok',
            'date_time' => '2026-06-20 06:00:00',
            'latitude' => '13.756331',
            'longitude' => '100.501762',
            'is_favorite' => '1',
            'images' => [
                UploadedFile::fake()->createWithContent(
                    'movement.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
                ),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.memory.trip_id', $routeTrip->id)
            ->assertJsonPath('data.memory.title', 'Android upload')
            ->assertJsonPath('data.memory.is_favorite', true)
            ->assertJsonCount(1, 'data.memory.photos');

        $memory = Memory::query()->where('title', 'Android upload')->firstOrFail();

        $this->assertSame($routeTrip->id, $memory->trip_id);
        $this->assertTrue($memory->is_favorite);
        $this->assertDatabaseHas('memory_photos', [
            'memory_id' => $memory->id,
            'photo_order' => 0,
        ]);
        Storage::disk('public')->assertExists($memory->photos()->firstOrFail()->photo_path);
    }

    public function test_user_can_upload_photos_to_movement(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Photo trip',
        ]);
        $memory = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'title' => 'Photo memory',
        ]);

        $response = $this->post('/api/v1/movements/'.$memory->id.'/photos', [
            'photos' => [
                UploadedFile::fake()->createWithContent(
                    'first.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
                ),
                UploadedFile::fake()->createWithContent(
                    'second.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
                ),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['data' => ['photos' => [['id', 'url', 'order']]]]);

        $memory->refresh();

        $this->assertCount(2, $memory->photos);

        foreach ($memory->photos as $photo) {
            $this->assertStringStartsWith('memory_photos/', $photo->photo_path);
            Storage::disk('public')->assertExists($photo->photo_path);
        }
    }

    public function test_owner_can_delete_photo_and_file_but_other_users_cannot(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private photo trip',
        ]);
        $memory = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
            'title' => 'Private photo memory',
        ]);

        $path = UploadedFile::fake()->createWithContent(
            'private.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        )->store('memory_photos', 'public');
        $photo = $memory->photos()->create([
            'photo_path' => $path,
            'photo_order' => 0,
        ]);

        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/v1/photos/'.$photo->id)->assertNotFound();
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('memory_photos', ['id' => $photo->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/photos/'.$photo->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('memory_photos', ['id' => $photo->id]);
    }

    public function test_user_can_update_profile_with_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/profile', [
            'name' => 'New Name',
            'bio' => 'Travel notes',
            'location' => 'Bangkok',
            'avatar' => UploadedFile::fake()->createWithContent(
                'avatar.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
            ),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.name', 'New Name')
            ->assertJsonPath('data.user.bio', 'Travel notes')
            ->assertJsonPath('data.user.location', 'Bangkok')
            ->assertJsonStructure(['data' => ['user' => ['avatar_url']]]);

        $user->refresh()->load('profile');

        $this->assertStringStartsWith('avatars/', $user->profile->avatar);
        Storage::disk('public')->assertExists($user->profile->avatar);
        $this->assertSame(asset('storage/'.$user->profile->avatar), $response->json('data.user.avatar_url'));
    }

    public function test_authenticated_user_can_delete_account_and_related_data(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $user->createToken('delete-account-test')->accessToken;
        $otherToken = $otherUser->createToken('other-token')->accessToken;

        $avatarPath = UploadedFile::fake()->createWithContent(
            'avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        )->store('avatars', 'public');
        $photoPath = UploadedFile::fake()->createWithContent(
            'photo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        )->store('memory_photos', 'public');
        $coverPath = UploadedFile::fake()->createWithContent(
            'cover.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        )->store('trip_covers', 'public');

        $user->profile()->create([
            'avatar' => $avatarPath,
            'bio' => 'Delete me',
            'location' => 'Bangkok',
        ]);
        $trip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Account delete trip',
            'cover_photo' => $coverPath,
        ]);
        $memory = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'title' => 'Account delete memory',
        ]);
        $photo = $memory->photos()->create([
            'photo_path' => $photoPath,
            'photo_order' => 0,
        ]);
        $otherTrip = Trip::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other trip',
        ]);

        $this->actingAs($user->withAccessToken($token), 'sanctum');

        $this->deleteJson('/api/v1/account')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
        $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
        $this->assertDatabaseMissing('memory_photos', ['id' => $photo->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);

        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('trips', ['id' => $otherTrip->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);

        Storage::disk('public')->assertMissing($avatarPath);
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_user_can_create_update_list_and_publish_draft_memory(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Draft trip',
        ]);

        $createResponse = $this->postJson('/api/v1/drafts', [
            'note' => 'Loose idea before publishing',
            'place' => 'Bangkok',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('message', 'Draft saved successfully.')
            ->assertJsonPath('data.draft.status', 'draft')
            ->assertJsonPath('data.movement.status', 'draft')
            ->assertJsonPath('data.memory.status', 'draft')
            ->assertJsonPath('data.draft.is_draft', true)
            ->assertJsonPath('data.draft.title', null)
            ->assertJsonPath('data.draft.trip_id', null);

        $draftId = $createResponse->json('data.draft.id');

        $this->getJson('/api/v1/movements')
            ->assertOk()
            ->assertJsonCount(0, 'data.movements');

        $this->getJson('/api/v1/movements?status=draft')
            ->assertOk()
            ->assertJsonCount(0, 'data.movements');

        $this->getJson('/api/v1/drafts')
            ->assertOk()
            ->assertJsonCount(1, 'data.drafts')
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.status', 'draft')
            ->assertJsonPath('data.drafts.0.id', $draftId);

        $this->putJson('/api/v1/drafts/'.$draftId, [
            'title' => 'Ready memory',
            'trip_id' => $trip->id,
            'note' => 'Now it has enough detail',
        ])
            ->assertOk()
            ->assertJsonPath('data.draft.title', 'Ready memory')
            ->assertJsonPath('data.movement.title', 'Ready memory')
            ->assertJsonPath('data.memory.title', 'Ready memory')
            ->assertJsonPath('data.draft.trip_id', $trip->id);

        $this->postJson('/api/v1/drafts/'.$draftId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.draft.status', 'published')
            ->assertJsonPath('data.movement.status', 'published')
            ->assertJsonPath('data.memory.status', 'published')
            ->assertJsonPath('data.draft.is_draft', false);

        $this->getJson('/api/v1/drafts')
            ->assertOk()
            ->assertJsonCount(0, 'data.drafts');

        $this->getJson('/api/v1/movements')
            ->assertOk()
            ->assertJsonCount(1, 'data.movements')
            ->assertJsonPath('data.movements.0.id', $draftId);
    }

    public function test_user_can_create_list_publish_and_delete_trip_drafts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draftResponse = $this->postJson('/api/v1/trips/drafts', [
            'description' => 'Trip idea before title exists',
            'location' => 'Cambodia',
        ]);

        $draftResponse
            ->assertCreated()
            ->assertJsonPath('message', 'Trip draft saved successfully.')
            ->assertJsonPath('data.draft.status', 'draft')
            ->assertJsonPath('data.trip.status', 'draft')
            ->assertJsonPath('data.trip.is_draft', true);

        $draftId = $draftResponse->json('data.trip.id');

        $this->getJson('/api/v1/trips')
            ->assertOk()
            ->assertJsonCount(0, 'data.trips');

        $this->getJson('/api/v1/trips/drafts')
            ->assertOk()
            ->assertJsonCount(1, 'data.trips')
            ->assertJsonPath('data.trips.0.id', $draftId);

        $this->putJson('/api/v1/trips/drafts/'.$draftId, [
            'title' => 'Updated trip draft',
            'location' => 'Siem Reap',
        ])
            ->assertOk()
            ->assertJsonPath('data.trip.title', 'Updated trip draft')
            ->assertJsonPath('data.trip.location', 'Siem Reap')
            ->assertJsonPath('data.trip.status', 'draft');

        $this->postJson('/api/v1/trips/drafts/'.$draftId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.trip.status', 'planned')
            ->assertJsonPath('data.trip.is_draft', false);

        $emptyDraftId = $this->postJson('/api/v1/trips/drafts', [
            'title' => '',
        ])
            ->assertCreated()
            ->assertJsonPath('data.trip.title', 'Untitled Trip')
            ->json('data.trip.id');

        $this->getJson('/api/v1/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data.trips')
            ->assertJsonPath('data.trips.0.id', $draftId);

        $this->deleteJson('/api/v1/trips/drafts/'.$emptyDraftId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('trips', ['id' => $emptyDraftId]);
    }

    public function test_user_cannot_publish_or_delete_another_users_trip_draft(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $draft = Trip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private trip draft',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/v1/trips/drafts/'.$draft->id)->assertNotFound();
        $this->postJson('/api/v1/trips/drafts/'.$draft->id.'/publish')->assertNotFound();

        $this->assertDatabaseHas('trips', [
            'id' => $draft->id,
            'status' => 'draft',
        ]);
    }

    public function test_publish_draft_requires_title_trip_and_owned_trip(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $otherTrip = Trip::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other trip',
        ]);

        $draftId = $this->postJson('/api/v1/drafts')
            ->assertCreated()
            ->json('data.draft.id');

        $this->postJson('/api/v1/drafts/'.$draftId.'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'trip_id']);

        $this->postJson('/api/v1/drafts/'.$draftId.'/publish', [
            'title' => 'Not mine',
            'trip_id' => $otherTrip->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('memories', [
            'id' => $draftId,
            'status' => 'draft',
        ]);
    }

    public function test_user_cannot_access_another_users_draft(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Owner trip',
        ]);
        $draft = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
            'title' => 'Private draft',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/drafts/'.$draft->id)->assertNotFound();
        $this->putJson('/api/v1/drafts/'.$draft->id, ['title' => 'Changed'])->assertNotFound();
        $this->postJson('/api/v1/drafts/'.$draft->id.'/publish')->assertNotFound();
        $this->deleteJson('/api/v1/drafts/'.$draft->id)->assertNotFound();
    }

    public function test_user_can_delete_draft_and_related_photo_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $draftId = $this->post('/api/v1/drafts', [
            'photos' => [
                UploadedFile::fake()->createWithContent(
                    'draft.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
                ),
            ],
        ])
            ->assertCreated()
            ->json('data.draft.id');

        $draft = Memory::query()->with('photos')->findOrFail($draftId);
        $photo = $draft->photos->first();

        Storage::disk('public')->assertExists($photo->photo_path);

        $this->deleteJson('/api/v1/drafts/'.$draftId)
            ->assertOk()
            ->assertJsonPath('success', true);

        Storage::disk('public')->assertMissing($photo->photo_path);
        $this->assertDatabaseMissing('memories', ['id' => $draftId]);
        $this->assertDatabaseMissing('memory_photos', ['id' => $photo->id]);
    }

    public function test_user_cannot_access_another_users_trip_or_movement(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $trip = Trip::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private trip',
        ]);
        $memory = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $owner->id,
            'title' => 'Private memory',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/trips/'.$trip->id)->assertNotFound();
        $this->getJson('/api/v1/movements/'.$memory->id)->assertNotFound();
    }

    public function test_favorite_toggle_and_stats_endpoints_work(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = Trip::query()->create([
            'user_id' => $user->id,
            'title' => 'Kampot',
        ]);
        $memory = Memory::query()->create([
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'title' => 'River',
            'address' => 'Cambodia',
        ]);

        $this->patchJson('/api/v1/trips/'.$trip->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('data.trip.is_favorite', true);

        $this->patchJson('/api/v1/movements/'.$memory->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('data.memory.is_favorite', true);

        $this->getJson('/api/v1/user/stats')
            ->assertOk()
            ->assertJsonPath('data.total_trips', 1)
            ->assertJsonPath('data.total_memories', 1)
            ->assertJsonPath('data.unique_countries_visited', 1);
    }
}
