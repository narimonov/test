<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Getting a hired driver to orientation. A carrier can book through us or
 * record a booking it made elsewhere — the second case still belongs here so
 * onboarding knows the travel is handled.
 */
class CreateTravelBookingsTable extends Migration
{
    public function up()
    {
        Schema::create('travel_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_onboarding_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider');                       // duffel | external
            $table->string('provider_reference')->nullable();
            $table->string('booking_reference')->nullable();   // airline record locator

            $table->string('origin', 4)->nullable();
            $table->string('destination', 4)->nullable();
            $table->date('depart_on')->nullable();
            $table->string('carrier_name')->nullable();        // operating airline
            $table->string('flight_number')->nullable();
            $table->timestamp('departs_at')->nullable();
            $table->timestamp('arrives_at')->nullable();

            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->string('status')->default('held');         // held | booked | cancelled | recorded
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['carrier_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('travel_bookings');
    }
}
