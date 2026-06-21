<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('location')->nullable()->after('bio');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->string('category')->nullable()->after('cover_photo');
            $table->boolean('is_favorite')->default(false)->after('category');
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dateTime('date_time')->nullable()->after('note');
            $table->string('place')->nullable()->after('date_time');
            $table->boolean('is_favorite')->default(false)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn(['date_time', 'place', 'is_favorite']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_favorite']);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
