<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecruitingFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('driver')->after('email');
            $table->string('phone')->nullable()->unique()->after('role');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('verification_code')->nullable();
            $table->string('verification_channel')->nullable();
            $table->timestamp('verification_code_expires_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'phone',
                'phone_verified_at',
                'verification_code',
                'verification_channel',
                'verification_code_expires_at',
            ]);
        });
    }
}
