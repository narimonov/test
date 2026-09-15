<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The licence number itself, needed to order a motor vehicle record.
 *
 * This is personal information under the DPPA, so the model hides it from API
 * responses and only the last four digits are ever shown.
 */
class AddCdlNumberToDriverProfilesTable extends Migration
{
    public function up()
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->string('cdl_number')->nullable()->after('cdl_state');
        });
    }

    public function down()
    {
        Schema::table('driver_profiles', function (Blueprint $table) {
            $table->dropColumn('cdl_number');
        });
    }
}
