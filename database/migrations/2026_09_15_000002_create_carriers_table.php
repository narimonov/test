<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarriersTable extends Migration
{
    public function up()
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('mc_number')->nullable();
            $table->string('dot_number')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->text('about')->nullable();
            $table->unsignedSmallInteger('fleet_size')->nullable();

            // Billing — carrier tomoni pul to'lab ishlatadi.
            $table->string('subscription_plan')->default('free');
            $table->string('subscription_status')->default('inactive');
            $table->timestamp('subscription_expires_at')->nullable();

            // Kompaniyaning o'z scoring sozlamalari (config/driver_scoring.php ustidan yoziladi).
            $table->json('scoring_overrides')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('carriers');
    }
}
