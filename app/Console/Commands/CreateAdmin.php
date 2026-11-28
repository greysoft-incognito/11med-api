<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Identity\PortalAccounts;
use App\Services\Staff\StaffAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Opens a super admin account and emails its first-password link, which is also printed for the operator.
 */
#[Signature('app:create-admin {email} {name}')]
#[Description('Open an 11med super admin account and send its first-password link')]
class CreateAdmin extends Command
{
    public function handle(PortalAccounts $accounts, StaffAccounts $staff): int
    {
        $email = (string) $this->argument('email');

        if ($accounts->findByEmail($email) !== null || User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists()) {
            $this->error("An account already uses {$email}.");

            return self::FAILURE;
        }

        $link = $staff->openSuperAdmin($email, (string) $this->argument('name'));

        $this->info("Opened {$email}. The first-password link, valid for seven days:");
        $this->line($link);

        return self::SUCCESS;
    }
}
