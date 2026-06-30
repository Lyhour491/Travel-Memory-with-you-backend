<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'location')) {
                $table->string('location')->nullable()->after('description');
            }
        });

        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'title')) {
                $table->string('title', 150)->nullable()->change();
            }

            if (Schema::hasColumn('trips', 'status')) {
                $table->string('status')->default('planned')->change();
            } else {
                $table->string('status')->default('planned')->after('cover_photo');
            }
        });

        Schema::table('memories', function (Blueprint $table) {
            if (! Schema::hasColumn('memories', 'status')) {
                $table->string('status')->default('published');
            }
        });

        Schema::table('memories', function (Blueprint $table) {
            if (Schema::hasColumn('memories', 'title')) {
                $table->string('title', 150)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            if (Schema::hasColumn('memories', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
