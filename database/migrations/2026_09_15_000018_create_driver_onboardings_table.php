<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding starts the moment a driver is hired. The step list comes from
 * config/onboarding.php, but each driver gets their own copy so changing the
 * template later does not rewrite someone's history.
 */
class CreateDriverOnboardingsTable extends Migration
{
    public function up()
    {
        Schema::create('driver_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();

            $table->string('track')->default('company_driver');
            $table->string('status')->default('in_progress');  // in_progress | completed | cancelled
            $table->date('start_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_profile_id', 'carrier_id']);
        });

        Schema::create('driver_onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_onboarding_id')->constrained()->cascadeOnDelete();

            $table->string('key');
            $table->string('label');
            $table->string('stage');                            // screening | compliance | training | equipment
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_required')->default(true);

            // Who is expected to act: carrier | driver | platform
            $table->string('owner')->default('carrier');

            $table->string('status')->default('pending');       // pending | in_progress | done | skipped | failed
            $table->text('note')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_onboarding_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_onboarding_steps');
        Schema::dropIfExists('driver_onboardings');
    }
}
