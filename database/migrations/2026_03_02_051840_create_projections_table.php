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
        Schema::create('projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('current_photo')->nullable(); 
    
            $table->string('time_horizon')->default('6 months'); 

            $table->float('current_bmi')->nullable();
            $table->float('current_weight')->nullable();
            
            $table->float('projected_bmi')->nullable();
            $table->float('projected_weight')->nullable();
            $table->string('projected_photo')->nullable();
            
            $table->integer('progress_percentage')->default(0); 
            
            $table->json('expected_changes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projections');
    }
};
