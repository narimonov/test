<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationsTable extends Migration
{
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // hiring  = carrier <-> driver
            // support = user <-> AI, escalated to a human agent over Telegram
            // recruiting = carrier <-> our in-house recruiter (Pro plan)
            $table->string('type')->default('hiring');

            $table->foreignId('carrier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('job_post_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject')->nullable();
            $table->string('status')->default('open');        // open | closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['carrier_id', 'driver_profile_id']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();

            // Null for messages written by the AI assistant or a Telegram agent.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_type')->default('user');   // user | ai | agent | system
            $table->string('author_name')->nullable();

            $table->text('body')->nullable();

            // Set when a human agent answered from Telegram, so replies are not
            // delivered back into Telegram twice.
            $table->string('telegram_message_id')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
}
