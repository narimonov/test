<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the internet says about a carrier, gathered once its MC/DOT is known.
 * Kept as dated snapshots rather than overwritten, so a rating that moves is
 * visible as a change instead of quietly replacing the old number.
 */
class CreateCarrierReputationSnapshotsTable extends Migration
{
    public function up()
    {
        Schema::create('carrier_reputation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->cascadeOnDelete();

            $table->string('source');                     // google | fmcsa_safety | platform
            $table->string('source_label');
            $table->decimal('rating', 3, 2)->nullable();  // normalised to 0-5
            $table->unsignedInteger('review_count')->nullable();
            $table->string('url')->nullable();

            // Free-form facts a source provides that do not fit a rating,
            // e.g. FMCSA out-of-service percentages.
            $table->json('details')->nullable();

            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['carrier_id', 'source', 'fetched_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('carrier_reputation_snapshots');
    }
}
