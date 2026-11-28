<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An erased account may have held several kinds; the row keeps them all, comma separated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_erasures', function (Blueprint $table) {
            $table->renameColumn('role', 'kinds');
        });

        Schema::table('account_erasures', function (Blueprint $table) {
            $table->string('kinds', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_erasures', function (Blueprint $table) {
            $table->renameColumn('kinds', 'role');
        });
    }
};
