<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndividualPlansTable extends Migration
{
    public function up()
    {
        Schema::create('individual_plans', function (Blueprint $table) {
           $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('billing_cycle', ['days', 'monthly', 'annual', 'custom']);
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('duration')->nullable(); // trial days
            $table->json('features')->nullable();
            $table->boolean('status')->default(true);
            $table->softDeletes();
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('individual_plans');
    }
}
