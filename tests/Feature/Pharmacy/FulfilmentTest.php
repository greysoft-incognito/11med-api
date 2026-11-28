<?php

namespace Tests\Feature\Pharmacy;

use App\Enums\NotificationCategory;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\SilentNotifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\Fakes\FakePayments;
use Tests\TestCase;

class FulfilmentTest extends TestCase
{
    use MakesPartners;
    use MakesPharmacyOrders;

    private FakePayments $payments;

    private Organisation $pharmacy;

    private User $desk;

    /** @var list<array{title: string, body: string, target: ?string}> */
    private array $pushes = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();

        $this->payments = $this->fakePayments();
        $this->app->instance(Notifier::class, new class($this->pushes) extends SilentNotifier
        {
            public function __construct(private array &$pushes) {}

            public function send(User $to, NotificationCategory $category, string $title, string $body, array $data = []): ?string
            {
                $this->pushes[] = ['title' => $title, 'body' => $body, 'target' => $data['target_id'] ?? null];

                return null;
            }
        });

        [$this->pharmacy, $this->desk] = $this->approvedPartner();
    }

    public function test_a_delivery_moves_one_step_at_a_time_and_the_patient_hears_each(): void
    {
        $order = $this->paidOrder(['fulfilment' => 'delivery', 'address' => $this->address()]);
        $url = "api/v1/portal/pharmacy/orders/{$order->uuid}";

        $this->actingAs($this->desk)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.status', 'placed')
            ->assertJsonPath('data.prescription_verified', true)
            ->assertJsonPath('data.pharmacy_id', $this->pharmacy->uuid);

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertOk()
            ->assertJsonPath('data.status', 'preparing');

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'RIDER_REQUIRED')
            ->assertJsonPath('error.field', 'rider');

        $rider = ['name' => 'Tunde Bakare', 'phone' => '+2348031234567', 'vehicle' => 'Bike, LND 482 KJ'];

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance", ['rider' => $rider])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.rider', ['name' => 'Tunde Bakare', 'phone' => '08031234567', 'vehicle' => 'Bike, LND 482 KJ'])
            ->assertJsonStructure(['data' => ['ready_at', 'estimated_delivery_at']]);

        $this->actingAs($this->desk)
            ->postJson("{$url}/cancel", ['reason' => 'Out of stock now'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_STARTED');

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['data' => ['completed_at']]);

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_FINAL');

        $this->assertSame([
            'The pharmacy is preparing your order',
            'Your order is on its way',
            'Your order was delivered',
        ], array_column($this->pushes, 'title'));
        $this->assertStringContainsString('Tunde Bakare has picked it up, about 45 minutes away', $this->pushes[1]['body']);
        $this->assertSame($order->uuid, $this->pushes[0]['target']);

        $this->actingAs($order->patient)
            ->getJson("api/v1/pharmacy/orders/{$order->uuid}")
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.rider.name', 'Tunde Bakare');

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/summary')
            ->assertJsonPath('data.completed_today', 1)
            ->assertJsonPath('data.revenue_today_kobo', $order->total_kobo)
            ->assertJsonPath('data.new_orders', 0);
    }

    public function test_a_counter_pickup_is_listed_unpaid_and_paid_when_collected(): void
    {
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($this->pharmacy));
        $id = $this->checkout($patient, ['fulfilment' => 'pickup', 'channel' => 'counter'])[0]['id'];
        $url = "api/v1/portal/pharmacy/orders/{$id}";

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.payment.channel', 'counter')
            ->assertJsonPath('data.items.0.payment.status', 'pending');

        $this->actingAs($this->desk)->postJson("{$url}/advance")->assertOk();

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.rider', null);

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment.status', 'paid')
            ->assertJsonPath('data.payment.channel', 'counter');

        $this->assertSame('Your order is ready for pickup', $this->pushes[1]['title']);
    }

    public function test_the_pharmacy_cancels_with_a_reason_and_the_order_is_refunded(): void
    {
        $order = $this->paidOrder();
        $url = "api/v1/portal/pharmacy/orders/{$order->uuid}";

        $this->actingAs($this->desk)
            ->postJson("{$url}/cancel", ['reason' => 'no'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reason');

        $this->actingAs($this->desk)
            ->postJson("{$url}/cancel", ['reason' => 'We have run out of this brand.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation', ['by' => 'pharmacy', 'reason' => 'We have run out of this brand.'])
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame('The pharmacy cancelled your order', $this->pushes[0]['title']);
        $this->assertStringContainsString('We have run out of this brand.', $this->pushes[0]['body']);

        $this->actingAs($this->desk)
            ->postJson("{$url}/advance")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_CANCELLED');

        $this->actingAs($this->desk)
            ->postJson("{$url}/cancel", ['reason' => 'Again, for the record.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_CANCELLED');

        $this->actingAs($order->patient)
            ->getJson("api/v1/pharmacy/orders/{$order->uuid}")
            ->assertJsonPath('data.cancellation.by', 'pharmacy');
    }

    public function test_the_list_filters_by_status_and_search_and_hides_unpaid_and_other_pharmacies_orders(): void
    {
        $paid = $this->paidOrder();
        $preparing = $this->paidOrder();
        $this->actingAs($this->desk)->postJson("api/v1/portal/pharmacy/orders/{$preparing->uuid}/advance")->assertOk();

        $unpaid = $this->patient();
        $this->addToCart($unpaid, $this->drug($this->pharmacy));
        $hidden = $this->checkout($unpaid)[0]['id'];

        [$other, $otherDesk] = $this->approvedPartner();

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders')
            ->assertJsonPath('data.total', 2);

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders?status=placed')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $paid->uuid);

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders?status=open')
            ->assertJsonPath('data.total', 2);

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders?q=' . $paid->reference)
            ->assertJsonPath('data.total', 1);

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/orders?q=' . urlencode('+234' . substr($preparing->patient->phone, 1)))
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $preparing->uuid);

        $this->actingAs($this->desk)
            ->getJson('api/v1/portal/pharmacy/summary')
            ->assertJsonPath('data.new_orders', 1)
            ->assertJsonPath('data.in_progress', 1);

        $this->actingAs($this->desk)
            ->getJson("api/v1/portal/pharmacy/orders/{$hidden}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');

        $this->actingAs($otherDesk)
            ->postJson("api/v1/portal/pharmacy/orders/{$paid->uuid}/advance")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    }

    public function test_a_medicine_in_an_open_order_cannot_be_removed(): void
    {
        $order = $this->paidOrder();
        $drug = $order->items->first()->drug;

        $this->actingAs($this->desk)
            ->deleteJson("api/v1/portal/pharmacy/drugs/{$drug->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'DRUG_IN_OPEN_ORDER');

        $this->actingAs($this->desk)->postJson("api/v1/portal/pharmacy/orders/{$order->uuid}/cancel", ['reason' => 'Closing early today.'])->assertOk();

        $this->actingAs($this->desk)
            ->deleteJson("api/v1/portal/pharmacy/drugs/{$drug->uuid}")
            ->assertOk();

        $this->actingAs($order->patient)
            ->getJson("api/v1/pharmacy/orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.items.0.drug_id', $drug->uuid);
    }

    public function test_only_the_pharmacy_moves_its_orders(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($order->patient)
            ->postJson("api/v1/portal/pharmacy/orders/{$order->uuid}/advance")
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->getJson('api/v1/portal/pharmacy/orders')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function paidOrder(array $body = ['fulfilment' => 'pickup']): Order
    {
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($this->pharmacy));
        $id = $this->checkout($patient, $body)[0]['id'];

        $order = Order::query()->where('uuid', $id)->firstOrFail();
        $this->payments->settle($order);
        $this->pushes = [];

        return $order->refresh()->load(['items.drug', 'patient']);
    }
}
