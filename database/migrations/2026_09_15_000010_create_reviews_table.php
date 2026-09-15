<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    public function up()
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();

            // Who is being rated: driver or carrier. Only one is filled in.
            $table->string('subject_type');                 // driver | carrier
            $table->foreignId('driver_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained()->cascadeOnDelete();

            // A review needs a real working relationship behind it.
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating');           // 1-5
            $table->text('body')->nullable();
            $table->boolean('is_negative')->default(false);  // rating <= 2
            $table->string('status')->default('published');  // published | removed
            $table->timestamps();

            $table->index(['subject_type', 'driver_profile_id']);
            $table->index(['subject_type', 'carrier_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
}
