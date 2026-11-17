<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doctors' payouts (app spec §7.7): every 48 hours, one per doctor, from completed consultations at
 * the platform's commission. The account it goes to is copied at creation; the transfer is recorded
 * when the team settles it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->publicId();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('doctor_name', 160);
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_masked', 32)->nullable();
            $table->unsignedBigInteger('gross_kobo')->default(0);
            $table->decimal('commission_percent', 5, 2);
            $table->unsignedBigInteger('commission_kobo')->default(0);
            $table->unsignedBigInteger('net_kobo')->default(0);
            $table->unsignedInteger('consultation_count')->default(0);
            $table->string('status', 16);
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->timestampTz('paid_at')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['doctor_id', 'period_end']);
        });

        // The desk reads pending first, then the newest periods.
        DB::statement("ALTER TABLE payouts ADD COLUMN status_rank smallint GENERATED ALWAYS AS (CASE status WHEN 'pending' THEN 0 ELSE 1 END) STORED");
        DB::statement('CREATE INDEX payouts_desk_index ON payouts (status_rank, period_end DESC, id DESC)');

        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('rated_at')->constrained()->nullOnDelete();
            $table->index(['doctor_id', 'status', 'payout_id']);
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });

        Schema::dropIfExists('payouts');
    }
};
