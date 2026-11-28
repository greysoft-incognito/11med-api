<?php

namespace Tests\Feature\Notifications;

use App\Enums\AccountKind;
use App\Enums\NotificationCategory;
use App\Enums\PartnerKind;
use App\Models\AppNotification;
use App\Models\LabBooking;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Payments\Payments;
use Tests\Concerns\MakesLaboratories;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

class PartnerAlertsTest extends TestCase
{
    use MakesLaboratories;
    use MakesPharmacyOrders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = new FakePayments;
        $this->app->instance(Payments::class, $this->payments);
    }

    public function test_a_paid_order_reaches_its_pharmacy_and_a_patients_cancellation_follows(): void
    {
        [$pharmacy, $desk] = $this->approvedPartner();
        [, $elsewhere] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($pharmacy));

        $order = Order::query()
            ->where('uuid', $this->checkout($patient, ['fulfilment' => 'pickup'])[0]['id'])
            ->firstOrFail();

        $this->assertSame([], $this->titles($desk));

        $this->payments->settle($order);
        $this->payments->settle($order);

        $this->assertSame(["New order {$order->reference}"], $this->titles($desk));
        $this->assertSame($order->uuid, $this->latest($desk)->target_id);
        $this->assertSame(NotificationCategory::PharmacyOrder, $this->latest($desk)->category);
        $this->assertSame([], $this->titles($elsewhere));

        $this->actingAs($patient)
            ->postJson("api/v1/pharmacy/orders/{$order->uuid}/cancel")
            ->assertOk();

        $this->assertSame("Order {$order->reference} was cancelled", $this->latest($desk)->title);
    }

    public function test_a_counter_pickup_reaches_the_pharmacy_at_checkout(): void
    {
        [$pharmacy, $desk] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($pharmacy));

        $reference = $this->checkout($patient, ['fulfilment' => 'pickup', 'channel' => 'counter'])[0]['reference'];

        $this->assertSame(["New order {$reference}"], $this->titles($desk));
        $this->assertStringContainsString('counter', $this->latest($desk)->body);
    }

    public function test_an_unpaid_order_the_patient_cancels_tells_nobody(): void
    {
        [$pharmacy, $desk] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($pharmacy));

        $id = $this->checkout($patient, ['fulfilment' => 'pickup'])[0]['id'];

        $this->actingAs($patient)
            ->postJson("api/v1/pharmacy/orders/{$id}/cancel")
            ->assertOk();

        $this->assertSame([], $this->titles($desk));
    }

    public function test_every_account_of_the_organisation_hears_it(): void
    {
        [$pharmacy, $desk] = $this->approvedPartner();
        $colleague = User::factory()
            ->portal(AccountKind::Pharmacy)
            ->create(['organisation_id' => $pharmacy->id]);

        app(Notifier::class)->toOrganisation($pharmacy, NotificationCategory::PharmacyOrder, 'New order', 'Start it.', ['dedupe_key' => 'once']);
        app(Notifier::class)->toOrganisation($pharmacy, NotificationCategory::PharmacyOrder, 'New order', 'Start it.', ['dedupe_key' => 'once']);

        $this->assertSame(['New order'], $this->titles($desk));
        $this->assertSame(['New order'], $this->titles($colleague));
    }

    public function test_a_paid_booking_reaches_its_laboratory_and_a_cancellation_follows(): void
    {
        [$laboratory, $desk] = $this->laboratory(['FBC' => 300000]);
        $patient = $this->labPatient();

        $id = $this->actingAs($patient)
            ->postJson('api/v1/labs/bookings', $this->bookingBody($laboratory, ['FBC']))
            ->assertCreated()
            ->json('data.id');

        $booking = LabBooking::query()->where('uuid', $id)->firstOrFail();

        $this->assertSame([], $this->titles($desk));

        $this->payments->pay($booking);
        $this->payments->pay($booking);

        $this->assertSame(["New booking {$booking->reference}"], $this->titles($desk));
        $this->assertSame(NotificationCategory::LabBooking, $this->latest($desk)->category);
        $this->assertStringNotContainsString('FBC', $this->latest($desk)->body);

        $this->actingAs($patient)
            ->postJson("api/v1/labs/bookings/{$id}/cancel")
            ->assertOk();

        $this->assertSame("Booking {$booking->reference} was cancelled", $this->latest($desk)->title);
    }

    public function test_a_partner_application_reaches_the_team(): void
    {
        $admin = $this->admin();

        $this->postJson('api/v1/partners/apply', $this->application(['kind' => PartnerKind::Laboratory->value, 'name' => 'Synlab Yaba']))
            ->assertCreated();

        $this->assertSame(['A laboratory applied'], $this->titles($admin));
        $this->assertSame('Synlab Yaba is waiting for a decision.', $this->latest($admin)->body);
        $this->assertSame(NotificationCategory::PartnerApplication, $this->latest($admin)->category);
    }

    /**
     * @return list<string>
     */
    private function titles(User $user): array
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }

    private function latest(User $user): AppNotification
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();
    }
}
