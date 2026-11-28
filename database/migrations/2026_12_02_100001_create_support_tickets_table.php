<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A support conversation opened by an app or portal account, answered by the team. Erasing the
 * requester removes it with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 24)->index();
            $table->string('subject', 120);
            $table->string('status', 12)->index();
            $table->string('link_kind', 16)->nullable();
            $table->uuid('link_id')->nullable();
            $table->foreignId('assignee_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_activity_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['requester_id', 'last_activity_at', 'id']);
            $table->index(['status', 'last_activity_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
