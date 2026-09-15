<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlacklistAppealsTable extends Migration
{
    public function up()
    {
        Schema::create('blacklist_appeals', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');                  // driver | carrier
            $table->foreignId('driver_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();

            $table->text('reason');
            $table->string('status')->default('pending');    // pending | approved | rejected
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('blacklist_appeals');
    }
}
