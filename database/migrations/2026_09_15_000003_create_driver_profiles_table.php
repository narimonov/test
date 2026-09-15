<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();

            // Driver o'zi ro'yxatdan o'tsa user_id bo'ladi; recruiter qo'lda kiritsa null.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('self_signup'); // self_signup | manual | csv | import

            // Shaxsiy
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();

            // CDL
            $table->string('cdl_class', 1)->nullable();          // A | B | C
            $table->string('cdl_state', 2)->nullable();
            $table->date('cdl_issued_at')->nullable();
            $table->date('cdl_expires_at')->nullable();
            $table->json('endorsements')->nullable();            // hazmat, tanker, doubles, passenger, twic
            $table->date('medical_card_expires_at')->nullable();

            // Tajriba
            $table->decimal('years_experience', 4, 1)->default(0);
            $table->json('equipment_experience')->nullable();    // dry_van, reefer, flatbed, tanker, stepdeck, car_hauler
            $table->string('driver_type')->default('company_driver'); // company_driver | owner_operator | lease_purchase
            $table->string('preferred_route')->nullable();       // otr | regional | local | dedicated
            $table->unsignedSmallInteger('jobs_last_3_years')->default(0);
            $table->unsignedSmallInteger('longest_tenure_months')->default(0);
            $table->unsignedSmallInteger('unemployment_gap_months')->default(0);

            // Safety / MVR
            $table->unsignedTinyInteger('accidents_3y')->default(0);
            $table->unsignedTinyInteger('preventable_accidents_3y')->default(0);
            $table->unsignedTinyInteger('moving_violations_3y')->default(0);
            $table->boolean('dui_ever')->default(false);
            $table->date('dui_last_at')->nullable();
            $table->boolean('license_suspended_ever')->default(false);
            $table->string('sap_status')->default('none');       // none | in_program | completed
            $table->boolean('failed_drug_test_ever')->default(false);
            $table->boolean('can_pass_drug_test')->default(true);

            // Ish sharoiti
            $table->string('work_authorization')->nullable();    // us_citizen | green_card | ead | other
            $table->boolean('willing_to_relocate')->default(false);
            $table->date('available_from')->nullable();
            $table->unsignedInteger('desired_pay_cents')->nullable();
            $table->string('desired_pay_unit')->nullable();      // per_mile | per_week | percentage

            // Qo'shimcha
            $table->string('resume_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');            // new | contacted | screening | hired | rejected
            $table->boolean('is_searchable')->default(true);

            $table->timestamps();

            $table->index(['state', 'cdl_class']);
            $table->index('years_experience');
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_profiles');
    }
}
