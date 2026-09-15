<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDriverDocumentsTable extends Migration
{
    public function up()
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // cdl | medical_card

            // The original stays on the private disk, only to be processed.
            $table->string('original_path')->nullable();
            // The redacted, watermarked PDF — the only thing a carrier sees.
            $table->string('pdf_path')->nullable();

            $table->string('watermark_text')->default('recruiting');
            $table->json('redactions')->nullable();       // [{x,y,w,h}] relative, 0..1
            $table->string('status')->default('pending'); // pending | processing | ready | failed
            $table->string('failure_reason')->nullable();
            $table->date('document_expires_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_profile_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_documents');
    }
}
