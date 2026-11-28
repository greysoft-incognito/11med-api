<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The terms and privacy policies the clients show, written by the team in Markdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->string('slug', 60);
            $table->string('title', 120);
            $table->text('body');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX documents_slug_unique ON documents (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
