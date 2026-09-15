<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal recruiting — part of the Pro plan. A carrier describes what it
 * needs, our recruiter works the request in a conversation and refers drivers.
 */
class CreateRecruitingRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('recruiting_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('brief');
            $table->unsignedSmallInteger('drivers_needed')->default(1);
            $table->string('status')->default('open');   // open | in_progress | fulfilled | closed
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('driver_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruiting_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('sent');   // sent | accepted | declined | hired
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['recruiting_request_id', 'driver_profile_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_referrals');
        Schema::dropIfExists('recruiting_requests');
    }
}
