<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFmcsaFieldsToCarriersTable extends Migration
{
    public function up()
    {
        Schema::table('carriers', function (Blueprint $table) {
            // Straight from FMCSA; we do not trust the name a user types.
            $table->string('fmcsa_legal_name')->nullable()->after('dot_number');
            $table->string('fmcsa_dba_name')->nullable()->after('fmcsa_legal_name');
            $table->string('fmcsa_status')->nullable()->after('fmcsa_dba_name');
            $table->boolean('allowed_to_operate')->default(false)->after('fmcsa_status');

            // The confirmation code goes here, not to anything the user typed.
            $table->string('fmcsa_phone')->nullable()->after('allowed_to_operate');
            $table->string('fmcsa_email')->nullable()->after('fmcsa_phone');

            $table->timestamp('fmcsa_checked_at')->nullable()->after('fmcsa_email');
            $table->timestamp('fmcsa_verified_at')->nullable()->after('fmcsa_checked_at');
            $table->json('fmcsa_snapshot')->nullable()->after('fmcsa_verified_at');

            // Moderation
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamp('blacklisted_at')->nullable();
            $table->string('blacklist_reason')->nullable();
            // Set when an appeal succeeds; negative reviews before this date
            // stop counting towards a new blacklist.
            $table->timestamp('blacklist_cleared_at')->nullable();

            $table->index('fmcsa_status');
        });
    }

    public function down()
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn([
                'fmcsa_legal_name', 'fmcsa_dba_name', 'fmcsa_status', 'allowed_to_operate',
                'fmcsa_phone', 'fmcsa_email', 'fmcsa_checked_at', 'fmcsa_verified_at',
                'fmcsa_snapshot', 'blocked_at', 'blocked_reason', 'blacklisted_at',
                'blacklist_reason', 'blacklist_cleared_at',
            ]);
        });
    }
}
