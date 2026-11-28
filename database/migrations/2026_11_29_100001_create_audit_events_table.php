<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the team did, one row per admin write. Rows are never changed; the actor's name is kept as
 * written so the row still reads after the account goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 120);
            $table->string('action', 64)->index();
            $table->string('subject_type', 40);
            $table->string('subject_id', 64);
            $table->jsonb('meta');
            $table->timestampTz('created_at')->index();

            $table->index(['actor_id', 'id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
