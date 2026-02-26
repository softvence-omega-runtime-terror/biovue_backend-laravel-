<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlanPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('plan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // who made the payment


               $table->foreignId('plan_id')
                  ->nullable()
                  ->constrained('subscription_plans')
                  ->onDelete('cascade');
                  
            // Plan type: 'individual' or 'professional'
            $table->enum('plan_type', ['individual', 'professional']); 

                 
         
                    $table->string('payment_method')->default('stripe');
                    $table->string('transaction_id')->unique();
                    $table->decimal('amount', 10, 2);
                    $table->string('currency', 10)->default('usd');
                    $table->string('platform')->default('web'); // 'web','app'

                    $table->string('status')->default('unpaid'); // unpaid, paid, failed

                    $table->timestamps();
                    
                    $table->softDeletes();
           
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_payments');
    }
}