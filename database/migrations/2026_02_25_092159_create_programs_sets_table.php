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
        Schema::create('programs_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); 
            $table->string('duration')->nullable();
            $table->string('primary_goal')->nullable(); 
            $table->string('target_intensity')->nullable(); 
            $table->json('habit_focus_areas')->nullable(); 
            $table->json('program_focus')->nullable(); 
            $table->integer('calories')->nullable(); 
            $table->integer('protein')->nullable(); 
            $table->integer('carbs')->nullable(); 
            $table->integer('fat')->nullable(); 
            $table->json('supplement_recommendation')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs_sets');
    }
};
