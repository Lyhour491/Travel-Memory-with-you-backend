<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_trip_and_movement(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tripResponse = $this->postJson('/api/trips', [
            'title' => 'Siem Reap',
            'description' => 'Temple visit',
            'category' => 'Culture',
        ]);

        $tripResponse
            ->assertCreated()
            ->assertJsonPath('data.trip.title', 'Siem Reap')
            ->assertJsonPath('data.trip.category', 'Culture');

        $tripId = $tripResponse->json('data.trip.id');

        $movementResponse = $this->postJson('/api/movements', [
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

        $this->getJson('/api/movements?trip_id='.$tripId)
            ->assertOk()
            ->assertJsonPath('data.trip_id', $tripId)
            ->assertJsonCount(1, 'data.movements');
    }

    public function test_global_movement_store_requires_trip_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/movements', [
            'title' => 'Missing trip',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trip_id');
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

        $this->getJson('/api/trips/'.$trip->id)->assertNotFound();
        $this->getJson('/api/movements/'.$memory->id)->assertNotFound();
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

        $this->patchJson('/api/trips/'.$trip->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('data.trip.is_favorite', true);

        $this->patchJson('/api/movements/'.$memory->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('data.memory.is_favorite', true);

        $this->getJson('/api/user/stats')
            ->assertOk()
            ->assertJsonPath('data.total_trips', 1)
            ->assertJsonPath('data.total_memories', 1)
            ->assertJsonPath('data.unique_countries_visited', 1);
    }
}
