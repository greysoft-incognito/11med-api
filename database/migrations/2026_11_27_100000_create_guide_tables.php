<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The guide's conversation (app handover §11): one live conversation per patient and its messages.
 * Message bodies are encrypted; starting over, erasure and the retention prune remove rows for real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_conversations', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('last_message_at');
            $table->timestampsTz();

            $table->unique('user_id');
            $table->index('last_message_at');
        });

        Schema::create('guide_messages', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('conversation_id')->constrained('guide_conversations')->cascadeOnDelete();
            $table->string('sender', 8);
            $table->text('body');
            $table->jsonb('actions');
            $table->string('urgency', 16);
            $table->jsonb('suggestions');
            $table->timestampTz('sent_at');
            $table->timestampsTz();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_messages');
        Schema::dropIfExists('guide_conversations');
    }
};
