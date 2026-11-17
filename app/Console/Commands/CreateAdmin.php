<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Identity\PortalAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Opens a team account and emails its first-password link, which is also printed for the operator.
 */
#[Signature('app:create-admin {email} {name}')]
#[Description('Open an 11med team account and send its first-password link')]
class CreateAdmin extends Command
{
    public function handle(PortalAccounts $accounts): int
    {
        $email = (string) $this->argument('email');

        if ($accounts->findByEmail($email) !== null || User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
            $this->error("An account already uses {$email}.");

            return self::FAILURE;
        }

        $admin = User::create([
            'full_name' => (string) $this->argument('name'),
            'email' => $email,
        ]);

        $admin->assignRole(Role::Admin->value);

        $link = $accounts->welcome($admin);

        $this->info("Opened {$email}. The first-password link, valid for seven days:");
        $this->line($link);

        return self::SUCCESS;
    }
}
