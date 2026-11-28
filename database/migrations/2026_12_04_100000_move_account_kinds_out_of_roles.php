<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Account kinds leave Spatie for `account_kinds`, a person holding any number of them. The old
 * `admin` role is not a kind: a team member is marked by `users.staff_since` and their access is
 * their staff roles and permissions. Spatie's `roles` table then holds staff roles only, named by
 * their key or public id.
 */
return new class extends Migration
{
    private const KINDS = ['patient', 'doctor', 'pharmacy', 'laboratory'];

    public function up(): void
    {
        Schema::create('account_kinds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->index();
            $table->timestampTz('created_at')->nullable();
            $table->unique(['user_id', 'kind']);
        });

        $kinds = "'" . implode("','", self::KINDS) . "'";

        DB::statement(<<<SQL
            INSERT INTO account_kinds (user_id, kind, created_at)
            SELECT DISTINCT m.model_id, r.name, now()
            FROM model_has_roles m
            JOIN roles r ON r.id = m.role_id
            JOIN users u ON u.id = m.model_id
            WHERE m.model_type = ? AND r.name IN ({$kinds})
            SQL, [(new User)->getMorphClass()]);

        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('staff_since')->nullable()->index();
        });

        DB::statement(<<<'SQL'
            UPDATE users SET staff_since = users.created_at
            FROM model_has_roles m
            JOIN roles r ON r.id = m.role_id
            WHERE m.model_id = users.id AND m.model_type = ? AND r.name = 'admin' AND NOT r.is_staff
            SQL, [(new User)->getMorphClass()]);

        DB::table('roles')
            ->where('is_staff', false)
            ->delete();

        DB::statement("UPDATE roles SET name = substr(name, 7) WHERE name LIKE 'staff:%'");
        DB::statement('DROP INDEX IF EXISTS roles_staff_label_unique');
        DB::statement('CREATE UNIQUE INDEX roles_label_unique ON roles (lower(label))');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['is_staff']);
            $table->dropColumn('is_staff');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_staff')->default(true)->index();
        });

        DB::statement('DROP INDEX IF EXISTS roles_label_unique');
        DB::statement('CREATE UNIQUE INDEX roles_staff_label_unique ON roles (lower(label)) WHERE is_staff');
        DB::statement("UPDATE roles SET name = 'staff:' || name");

        foreach ([...self::KINDS, 'admin'] as $kind) {
            DB::table('roles')->insert(['name' => $kind, 'guard_name' => 'web', 'is_staff' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        DB::statement(<<<'SQL'
            INSERT INTO model_has_roles (role_id, model_type, model_id)
            SELECT r.id, ?, k.user_id
            FROM account_kinds k
            JOIN roles r ON r.name = k.kind AND NOT r.is_staff
            SQL, [(new User)->getMorphClass()]);

        DB::statement(<<<'SQL'
            INSERT INTO model_has_roles (role_id, model_type, model_id)
            SELECT a.id, ?, u.id
            FROM users u
            CROSS JOIN roles a
            WHERE u.staff_since IS NOT NULL AND a.name = 'admin' AND NOT a.is_staff
            SQL, [(new User)->getMorphClass()]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_since');
        });

        Schema::drop('account_kinds');
    }
};
