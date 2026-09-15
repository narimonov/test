<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobPostsTable extends Migration
{
    public function up()
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('route_type')->nullable();            // otr | regional | local | dedicated
            $table->string('driver_type')->default('company_driver');
            $table->string('equipment')->nullable();
            $table->unsignedInteger('pay_min_cents')->nullable();
            $table->unsignedInteger('pay_max_cents')->nullable();
            $table->string('pay_unit')->nullable();              // per_mile | per_week | percentage
            $table->json('requirements')->nullable();            // scoring knockout'larini job darajasida bekor qiladi
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_posts');
    }
}
