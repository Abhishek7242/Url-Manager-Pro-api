<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Create table only if it doesn't exist
        if (!Schema::hasTable('urls')) {
            Schema::create('urls', function (Blueprint $table) {
                $table->id();
                $table->string('user_id')->nullable()->foreignId()->constrained()->onDelete('cascade');
                $table->string('session_id')->nullable()->foreignId()->constrained()->onDelete('cascade');
                $table->string('title')->nullable();
                $table->text('url'); // main URL
                $table->text('description')->nullable();

                // JSON field for multiple tags
                $table->json('tags')->nullable();
                $table->date('reminder_at')->nullable();

                // status (active, archived, deleted, etc.)
                $table->enum('status', ['active', 'archived', 'deleted'])->default('active');

                // total click count
                $table->unsignedBigInteger('url_clicks')->default(0);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};