<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review is no longer published the moment it is written. The author has to
 * attach proof that the two sides actually worked together; an admin checks
 * that proof, contacts the other side, and only then does the review go live
 * and start counting towards a blacklist.
 */
class AddProofFieldsToReviewsTable extends Migration
{
    public function up()
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('counterparty_contacted_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('moderation_note')->nullable();
        });

        Schema::create('review_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            // rate_confirmation | employment_letter | settlement | paystub | other
            $table->string('kind')->default('other');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('review_proofs');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'submitted_at', 'counterparty_contacted_at', 'reviewed_at', 'moderation_note',
            ]);
        });
    }
}
