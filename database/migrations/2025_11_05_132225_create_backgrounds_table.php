<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('backgrounds', function (Blueprint $table) {
            $table->id();

            // Core fields
            $table->string('background', 1000); // can store hex, gradient CSS, image path, or JSON
            $table->string('type', 255); // solid, gradient, image, live
            $table->string('name', 255)->nullable(); // background name or label

            // Optional management fields
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backgrounds');
    }
};
