<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHiringFieldsToDriverProfilesTable extends Migration
{
    public function up()
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            // Exclusivity: once one carrier hires them, no other can.
            $table->foreignId('hired_carrier_id')->nullable()->after('status')
                ->constrained('carriers')->nullOnDelete();
            $table->timestamp('hired_at')->nullable()->after('hired_carrier_id');

            $table->timestamp('blacklisted_at')->nullable();
            $table->string('blacklist_reason')->nullable();
            $table->timestamp('blacklist_cleared_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hired_carrier_id');
            $table->dropColumn(['hired_at', 'blacklisted_at', 'blacklist_reason', 'blacklist_cleared_at']);
        });
    }
}
