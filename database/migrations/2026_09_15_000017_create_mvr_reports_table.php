<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor vehicle records.
 *
 * A record pulled recently is reused rather than ordered again — states charge
 * per pull and the data barely moves in a month. Reuse is recorded on the
 * report so it is always clear who saw what and when.
 */
class CreateMvrReportsTable extends Migration
{
    public function up()
    {
        Schema::create('mvr_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();

            // Who paid for the pull. Null for a record the driver brought with them.
            $table->foreignId('ordered_by_carrier_id')->nullable()->constrained('carriers')->nullOnDelete();

            $table->string('provider');                      // sambasafety | fake
            $table->string('provider_reference')->nullable();
            $table->string('state', 2);
            $table->string('licence_number_last4', 4)->nullable();

            $table->string('status')->default('ordered');    // ordered | completed | failed
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Summary pulled out of the report so scoring does not parse raw payloads.
            $table->unsignedTinyInteger('violations_count')->nullable();
            $table->unsignedTinyInteger('accidents_count')->nullable();
            $table->unsignedTinyInteger('suspensions_count')->nullable();
            $table->string('licence_status')->nullable();

            $table->unsignedInteger('cost_cents')->nullable();
            $table->json('payload')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['driver_profile_id', 'completed_at']);
        });

        Schema::create('mvr_report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mvr_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();

            // reused = an existing report was shared instead of a new pull
            $table->string('source')->default('ordered');    // ordered | reused
            $table->timestamps();

            $table->unique(['mvr_report_id', 'carrier_id']);
        });

        Schema::create('mvr_state_rates', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('state', 2);
            $table->unsignedInteger('cost_cents');
            $table->string('turnaround')->nullable();        // instant | same_day | 1-3_days
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'state']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('mvr_state_rates');
        Schema::dropIfExists('mvr_report_shares');
        Schema::dropIfExists('mvr_reports');
    }
}
