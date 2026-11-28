<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the team tells an audience: a title and a body, sent now or at a time, as notification rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('title', 120);
            $table->string('body', 1000);
            $table->string('audience', 16);
            $table->boolean('push')->default(true);
            $table->string('status', 16);
            $table->timestampTz('send_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->unsignedInteger('recipients')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['status', 'send_at']);
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
