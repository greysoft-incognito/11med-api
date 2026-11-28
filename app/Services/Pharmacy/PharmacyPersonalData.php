<?php

namespace App\Services\Pharmacy;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Identity\Export\PersonalData;
use App\Services\Identity\Export\PersonalDataWriter;

class PharmacyPersonalData implements PersonalData
{
    public function export(User $user, PersonalDataWriter $writer): void
    {
        $orders = Order::where('patient_id', $user->id);

        $writer->section('orders', 'Pharmacy orders', $orders->clone());
        $writer->section('order_items', 'What you ordered', OrderItem::whereIn('order_id', $orders->clone()->select('id')));
    }
}
