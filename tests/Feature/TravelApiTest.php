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
