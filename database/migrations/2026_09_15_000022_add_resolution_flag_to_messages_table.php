<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records whether the assistant actually answered a support question.
 *
 * Without this the "how many questions in a row have I failed" counter lives
 * only in memory, which resets on every request — so it never reaches the
 * handover threshold, or reaches it at the wrong time.
 */
class AddResolutionFlagToMessagesTable extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            // Null for anything not written by the assistant.
            $table->boolean('resolved_question')->nullable()->after('author_name');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('resolved_question');
        });
    }
}
