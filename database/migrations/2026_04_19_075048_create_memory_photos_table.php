<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->integer('photo_order')->default(0);
            $table->timestamps();

            $table->index(['memory_id', 'photo_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_photos');
    }
};