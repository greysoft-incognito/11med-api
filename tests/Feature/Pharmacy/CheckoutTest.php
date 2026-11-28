<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\HttpStatus;
use App\Enums\NotificationCategory;
use App\Enums\OrderStatus;
use App\Enums\PartnerKind;
use App\Exceptions\ApiException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Pharmacy\OrderPayables;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use MakesPartners;
    use MakesPharmacyOrders;

    private FakePayments $payments;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        $this->payments = $this->fakePayments();
    }

    public function test_checkout_makes_one_order_per_pharmacy_under_one_payment_and_empties_the_cart(): void
    {
        [$lagos] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Lagos', 'city' => 'Ikeja', 'address' => '12 Allen Avenue', 'state' => 'Lagos']);
        [$kano] = $this->approvedPartner(PartnerKind::Pharmacy, ['name' => 'Kano']);
        Pharmacy::query()->where('organisation_id', $kano->id)->update(['delivery_fee_kobo' => 80000]);

        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($lagos, ['name' => 'Amoxicillin', 'price_kobo' => 300000]), 2);
        $this->addToCart($patient, $this->drug($kano, ['name' => 'Vitamin C', 'price_kobo' => 100000]));

        $orders = $this->checkout($patient, ['fulfilment' => 'delivery', 'address' => $this->address(), 'channel' => 'card']);

        $this->assertCount(2, $orders);
        $this->assertSame('Lagos', $orders[0]['pharmacy_name']);
        $this->assertSame('12 Allen Avenue, Ikeja, Lagos', $orders[0]['pharmacy_address']);
        $this->assertSame('08031234567', $orders[0]['pharmacy_phone']);
        $this->assertSame('placed', $orders[0]['status']);
        $this->assertSame(600000, $orders[0]['subtotal_kobo']);
        $this->assertSame(150000, $orders[0]['delivery_fee_kobo']);
        $this->assertSame(750000, $orders[0]['total_kobo']);
        $this->assertSame(80000, $orders[1]['delivery_fee_kobo']);
        $this->assertSame('Blue gate', $orders[0]['address']['directions']);
        $this->assertSame('Amoxicillin', $orders[0]['items'][0]['drug_name']);
        $this->assertSame(2, $orders[0]['items'][0]['quantity']);
        $this->assertMatchesRegularExpression('/^ORD-[A-Z2-9]{8}$/', $orders[0]['reference']);
        $this->assertSame($orders[0]['payment']['reference'], $orders[1]['payment']['reference']);
        $this->assertSame('pending', $orders[0]['payment']['status']);
        $this->assertNotNull($orders[0]['payment']['authorization_url']);
        $this->assertNull($orders[0]['rider']);
        $this->assertNull($orders[0]['cancellation']);

        $this->assertSame(0, CartItem::query()->where('patient_id', $patient->id)->count());

        $this->actingAs($patient)
            ->getJson('api/v1/pharmacy/orders')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/orders/{$orders[1]['id']}")
            ->assertOk()
            ->assertJsonPath('data.pharmacy_name', 'Kano')
            ->assertJsonPath('data.payment.reference', $orders[0]['payment']['reference']);
    }

    public function test_a_pickup_has_no_address_or_fee_and_may_be_paid_at_the_counter(): void
    {
        [$pharmacy] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($pharmacy));

        $orders = $this->checkout($patient, ['fulfilment' => 'pickup', 'address' => $this->address(), 'channel' => 'counter']);

        $this->assertNull($orders[0]['address']);
        $this->assertSame(0, $orders[0]['delivery_fee_kobo']);
        $this->assertSame('counter', $orders[0]['payment']['channel']);
        $this->assertNull($orders[0]['payment']['authorization_url']);
        $this->assertSame('counter', Order::query()->first()->payment_channel->value);
    }

    public function test_checkout_refusals(): void
    {
        [$pharmacy] = $this->approvedPartner();
        $patient = $this->patient();

        $this->actingAs($patient)
            ->postJson('api/v1/pharmacy/orders', ['fulfilment' => 'pickup'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'CART_EMPTY');

        $rx = $this->drug($pharmacy, ['requires_prescription' => true]);
        $this->addToCart($patient, $rx);

        $this->actingAs($patient)
            ->postJson('api/v1/pharmacy/orders', ['fulfilment' => 'pickup'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PRESCRIPTION_REQUIRED')
            ->assertJsonPath('error.meta.blocked_items', [$rx->uuid]);

        $this->actingAs($patient)
            ->postJson('api/v1/pharmacy/orders', ['fulfilment' => 'delivery'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'address');

        $this->actingAs($patient)
            ->postJson('api/v1/pharmacy/orders', ['fulfilment' => 'delivery', 'address' => $this->address(), 'channel' => 'counter'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'COUNTER_IS_FOR_PICKUP')
            ->assertJsonPath('error.field', 'channel');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_the_webhooks_payment_marks_every_order_of_the_checkout_paid_with_one_receipt(): void
    {
        $notifier = Mockery::mock(Notifier::class);
        $notifier->shouldReceive('send')
            ->twice()
            ->withArgs(fn(User $to, NotificationCategory $category, string $title, string $body, array $data) => $category === NotificationCategory::Order
                && $title === 'Payment received'
                && str_starts_with($data['dedupe_key'], 'payment:PAY-'))
            ->andReturn(null);
        $notifier->shouldReceive('toPermission');
        $notifier->shouldReceive('toOrganisation')
            ->twice()
            ->withArgs(fn($organisation, NotificationCategory $category, string $title) => $category === NotificationCategory::PharmacyOrder
                && str_starts_with($title, 'New order '));
        $this->app->instance(Notifier::class, $notifier);

        [$lagos] = $this->approvedPartner();
        [$kano] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($lagos));
        $this->addToCart($patient, $this->drug($kano));

        $orders = $this->checkout($patient);

        $this->assertSame(0, Order::query()->visibleToPharmacy()->count());

        $targets = app(OrderPayables::class)->resolve($orders[1]['id'], $patient);

        $this->assertSame([$orders[1]['id'], $orders[0]['id']], array_map(fn(Order $order) => $order->uuid, $targets));

        foreach ($targets as $target) {
            $this->payments->settle($target);
        }

        $this->assertSame(2, Order::query()->visibleToPharmacy()->count());
        $this->assertSame('card', Order::query()->first()->payment_channel->value);

        $this->actingAs($patient)
            ->getJson("api/v1/pharmacy/orders/{$orders[0]['id']}")
            ->assertJsonPath('data.payment.status', 'paid');
    }

    public function test_the_patient_cancels_only_while_placed_and_is_refunded(): void
    {
        [$pharmacy] = $this->approvedPartner();
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($pharmacy));
        $this->addToCart($patient, $this->drug($pharmacy));

        $order = $this->checkout($patient)[0];
        $this->payments->settle(Order::query()->where('uuid', $order['id'])->first());

        $this->actingAs($patient)
            ->postJson("api/v1/pharmacy/orders/{$order['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation', ['by' => 'patient', 'reason' => ''])
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame([['target' => $order['id'], 'amount' => null]], $this->payments->refunds);

        $this->addToCart($patient, $this->drug($pharmacy));
        $next = $this->checkout($patient)[0];
        Order::query()->where('uuid', $next['id'])->update(['status' => OrderStatus::Preparing]);

        $this->actingAs($patient)
            ->postJson("api/v1/pharmacy/orders/{$next['id']}/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_CONFIRMED');

        $this->actingAs($this->patient())
            ->getJson("api/v1/pharmacy/orders/{$next['id']}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');

        $this->expectExceptionObject(new ApiException(HttpStatus::NOT_FOUND, 'ORDER_NOT_FOUND'));
        app(OrderPayables::class)->resolve($next['id'], $this->patient());
    }
}
