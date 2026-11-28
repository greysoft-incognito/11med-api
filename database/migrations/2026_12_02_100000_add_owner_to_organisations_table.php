<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The portal account that owns a pharmacy or laboratory and manages its staff. Approval makes the
 * contact's account the owner; existing partners get the account with the contact's email, or their first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('contact_email')->constrained('users')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE organisations o
            SET owner_id = COALESCE(
                (SELECT u.id FROM users u WHERE u.organisation_id = o.id AND u.deleted_at IS NULL AND lower(u.email) = lower(o.contact_email) ORDER BY u.id LIMIT 1),
                (SELECT u.id FROM users u WHERE u.organisation_id = o.id AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
