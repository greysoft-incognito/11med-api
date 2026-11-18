<?php

namespace Database\Seeders\Demo;

use App\Enums\PartnerKind;
use App\Enums\PartnerStatus;
use App\Events\Partners\PartnerApproved;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * An approved pharmacy or laboratory, as the team's approval leaves it, with a portal account
 * that signs in with the demo password. No welcome email is sent.
 */
class DemoPartners
{
    /**
     * @param  array{name: string, state: string, city: string, address: string}  $partner
     * @return array{Organisation, User}
     */
    public static function approve(PartnerKind $kind, array $partner, int $nth): array
    {
        $slug = Str::slug($partner['name']);

        $organisation = Organisation::create([
            'kind' => $kind,
            'name' => $partner['name'],
            'state' => $partner['state'],
            'city' => $partner['city'],
            'address' => $partner['address'],
            'licence_number' => strtoupper(substr($kind->value, 0, 3)) . '-DEMO-' . str_pad((string) $nth, 3, '0', STR_PAD_LEFT),
            'contact_name' => $partner['name'],
            'contact_phone' => '0803000' . ($kind === PartnerKind::Pharmacy ? '01' : '02') . str_pad((string) $nth, 2, '0', STR_PAD_LEFT),
            'contact_email' => "{$slug}@example.com",
            'status' => PartnerStatus::Approved,
            'applied_at' => now()->subDays(45),
            'reviewed_at' => now()->subDays(40),
        ]);

        $account = new User([
            'full_name' => $partner['name'],
            'email' => "{$slug}@example.com",
            'password' => DemoAccounts::PASSWORD,
        ]);
        $account->organisation_id = $organisation->id;
        $account->email_verified_at = now()->subDays(40);
        $account->save();
        $account->assignRole($kind->role()->value);

        PartnerApproved::dispatch($organisation);

        return [$organisation, $account];
    }
}
