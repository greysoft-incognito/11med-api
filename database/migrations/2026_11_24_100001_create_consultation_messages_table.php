<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consultation's chat (app spec §7.4): words, an attachment from the sender's own uploads, or both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_messages', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->string('sender', 8);
            $table->text('body');
            $table->foreignId('attachment_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestampTz('sent_at');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['consultation_id', 'sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_messages');
    }
};
