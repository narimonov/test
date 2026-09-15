<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();

            $table->string('provider');                       // stripe | payme | click
            $table->string('provider_reference')->nullable(); // session / transaction id
            $table->string('plan');                           // starter | growth | pro

            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');

            $table->string('status')->default('pending');     // pending | paid | failed | cancelled
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_reference']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
