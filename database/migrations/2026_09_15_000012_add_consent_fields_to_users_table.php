<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent records required by US law.
 *
 * - Privacy notice acceptance (CCPA/CPRA "notice at collection" and the state
 *   equivalents) is stored with the exact policy version the user agreed to.
 * - TCPA requires prior express consent before sending SMS, including the
 *   verification codes this app sends.
 * - FCRA and the DPPA both require a written disclosure and written
 *   authorisation before an MVR is pulled for employment purposes.
 *
 * Each consent keeps the timestamp and the IP it was given from, because a
 * consent you cannot evidence is the same as no consent.
 */
class AddConsentFieldsToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->string('privacy_version')->nullable();
            $table->timestamp('sms_consent_at')->nullable();
            $table->timestamp('mvr_consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_accepted_at', 'privacy_version',
                'sms_consent_at', 'mvr_consent_at', 'consent_ip',
            ]);
        });
    }
}
