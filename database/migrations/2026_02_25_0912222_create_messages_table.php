<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversations_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('reply_id')->nullable()->constrained('messages')->onDelete('cascade');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
