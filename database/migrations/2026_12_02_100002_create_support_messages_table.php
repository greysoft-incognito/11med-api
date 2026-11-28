<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One message of a support ticket, from the requester or the team. The body is encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->boolean('from_team');
            $table->text('body');
            $table->foreignId('attachment_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestampTz('sent_at');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['ticket_id', 'sent_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
