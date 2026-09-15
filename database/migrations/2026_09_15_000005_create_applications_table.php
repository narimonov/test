<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('applied');    // applied | screening | interview | hired | rejected
            $table->unsignedTinyInteger('score')->nullable(); // snapshot at application time
            $table->string('tier', 2)->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('knockouts')->nullable();
            $table->text('cover_note')->nullable();
            $table->timestamps();

            $table->unique(['job_post_id', 'driver_profile_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
}
