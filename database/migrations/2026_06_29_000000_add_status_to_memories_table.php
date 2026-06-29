<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->string('status')->default('published')->after('is_favorite');
            $table->dropForeign(['trip_id']);
            $table->foreignId('trip_id')->nullable()->change();
            $table->string('title', 150)->nullable()->change();
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
            $table->foreignId('trip_id')->nullable(false)->change();
            $table->string('title', 150)->nullable(false)->change();
            $table->dropColumn('status');
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
        });
    }
};
