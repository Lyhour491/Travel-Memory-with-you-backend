<?php

namespace Database\Seeders;

use App\Models\Memory;
use App\Models\MemoryPhoto;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@travelmemory.app'],
            [
                'name' => 'Sokha Traveler',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'bio' => 'Weekend explorer documenting temples, food, beaches, and city walks.',
                'location' => 'Phnom Penh, Cambodia',
                'home_city' => 'Phnom Penh',
                'home_country' => 'Cambodia',
            ]
        );

        $user->memories()->forceDelete();
        $user->trips()->forceDelete();

        $siemReap = $this->createTripWithMemories($user, [
            'title' => 'Siem Reap Temple Weekend',
            'description' => 'A short cultural trip focused on sunrise, local food, and ancient temples.',
            'category' => 'Culture',
            'status' => 'completed',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-12',
            'cover_photo' => 'seed/trips/siem-reap-cover.jpg',
            'is_favorite' => true,
        ], [
            [
                'title' => 'Sunrise at Angkor Wat',
                'note' => 'Arrived before dawn, found a quiet spot near the reflection pond, and watched the towers slowly appear.',
                'place' => 'Angkor Wat',
                'date_time' => '2026-05-10 05:35:00',
                'memory_date' => '2026-05-10',
                'location_name' => 'Angkor Wat',
                'latitude' => 13.4125000,
                'longitude' => 103.8670000,
                'address' => 'Cambodia',
                'is_favorite' => true,
                'photos' => ['seed/memories/angkor-sunrise-1.jpg', 'seed/memories/angkor-sunrise-2.jpg'],
            ],
            [
                'title' => 'Dinner on Pub Street',
                'note' => 'Tried fish amok and fresh mango shake after a long walking day.',
                'place' => 'Pub Street',
                'date_time' => '2026-05-10 19:20:00',
                'memory_date' => '2026-05-10',
                'location_name' => 'Pub Street',
                'latitude' => 13.3549000,
                'longitude' => 103.8552000,
                'address' => 'Cambodia',
                'is_favorite' => false,
                'photos' => ['seed/memories/pub-street-food.jpg'],
            ],
        ]);

        $this->createTripWithMemories($user, [
            'title' => 'Kampot Slow Travel',
            'description' => 'A calm riverside escape with pepper farms, sunset views, and mountain air.',
            'category' => 'Nature',
            'status' => 'completed',
            'start_date' => '2026-04-03',
            'end_date' => '2026-04-06',
            'cover_photo' => 'seed/trips/kampot-cover.jpg',
            'is_favorite' => false,
        ], [
            [
                'title' => 'Sunset by Kampot River',
                'note' => 'Rented a small boat and watched the sky turn orange behind Bokor Mountain.',
                'place' => 'Kampot River',
                'date_time' => '2026-04-04 17:45:00',
                'memory_date' => '2026-04-04',
                'location_name' => 'Kampot River',
                'latitude' => 10.6104000,
                'longitude' => 104.1815000,
                'address' => 'Cambodia',
                'is_favorite' => true,
                'photos' => ['seed/memories/kampot-river-sunset.jpg'],
            ],
            [
                'title' => 'Pepper Farm Visit',
                'note' => 'Learned how green, black, red, and white pepper all come from the same plant.',
                'place' => 'La Plantation',
                'date_time' => '2026-04-05 10:30:00',
                'memory_date' => '2026-04-05',
                'location_name' => 'La Plantation',
                'latitude' => 10.6348000,
                'longitude' => 104.2667000,
                'address' => 'Cambodia',
                'is_favorite' => false,
                'photos' => ['seed/memories/pepper-farm.jpg'],
            ],
        ]);

        $this->createTripWithMemories($user, [
            'title' => 'Bangkok Food Notes',
            'description' => 'Street food, river transport, markets, and a few city viewpoints.',
            'category' => 'Food',
            'status' => 'completed',
            'start_date' => '2026-03-14',
            'end_date' => '2026-03-18',
            'cover_photo' => 'seed/trips/bangkok-cover.jpg',
            'is_favorite' => true,
        ], [
            [
                'title' => 'Boat Ride to Wat Arun',
                'note' => 'Used the river ferry during golden hour. The temple looked bright against the evening sky.',
                'place' => 'Wat Arun',
                'date_time' => '2026-03-15 16:50:00',
                'memory_date' => '2026-03-15',
                'location_name' => 'Wat Arun',
                'latitude' => 13.7437000,
                'longitude' => 100.4889000,
                'address' => 'Thailand',
                'is_favorite' => true,
                'photos' => ['seed/memories/wat-arun.jpg'],
            ],
        ]);

        $this->createTripWithMemories($user, [
            'title' => 'Japan Spring Draft Plan',
            'description' => 'A planned route for Tokyo and Kyoto during cherry blossom season.',
            'category' => 'Adventure',
            'status' => 'planned',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-30',
            'cover_photo' => 'seed/trips/japan-cover.jpg',
            'is_favorite' => false,
        ], []);

        $this->command?->info('Demo account: demo@travelmemory.app / password123');
        $this->command?->info('Seeded '.$user->trips()->count().' trips and '.$user->memories()->count().' movements.');
    }

    /**
     * @param  array<string, mixed>  $tripData
     * @param  array<int, array<string, mixed>>  $memories
     */
    private function createTripWithMemories(User $user, array $tripData, array $memories): Trip
    {
        $trip = Trip::query()->create([
            ...$tripData,
            'user_id' => $user->id,
        ]);

        foreach ($memories as $memoryIndex => $memoryData) {
            $photos = $memoryData['photos'] ?? [];
            unset($memoryData['photos']);

            $memory = Memory::query()->create([
                ...$memoryData,
                'trip_id' => $trip->id,
                'user_id' => $user->id,
            ]);

            foreach ($photos as $photoIndex => $photoPath) {
                MemoryPhoto::query()->create([
                    'memory_id' => $memory->id,
                    'photo_path' => $photoPath,
                    'photo_order' => ($memoryIndex * 10) + $photoIndex,
                ]);
            }
        }

        return $trip;
    }
}
