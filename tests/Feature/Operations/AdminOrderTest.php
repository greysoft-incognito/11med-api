<?php

namespace Tests\Feature\Operations;

use App\Enums\AccountKind;
use App\Enums\AdminPermission;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLog;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesAdmins;
use Tests\Concerns\MakesPartners;
use Tests\Concerns\MakesPharmacyOrders;
use Tests\Fakes\FakePayments;
use Tests\Fakes\RecordingAuditLog;
use Tests\Fakes\RecordingNotifier;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use MakesAdmins;
    use MakesPartners;
    use MakesPharmacyOrders;

    private FakePayments $payments;

    private RecordingNotifier $notifier;

    private RecordingAuditLog $audit;

    private Organisation $pharmacy;

    private User $team;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        Notification::fake();
        $this->travelTo('2026-12-01T09:00:00Z');

        $this->payments = $this->fakePayments();
        $this->notifier = new RecordingNotifier;
        $this->audit = new RecordingAuditLog;
        $this->app->instance(Notifier::class, $this->notifier);
        $this->app->instance(AuditLog::class, $this->audit);

        [$this->pharmacy] = $this->approvedPartner(overrides: ['name' => 'Medplus Ikeja']);
        $this->team = $this->teamMember();
    }

    public function test_every_pharmacys_orders_are_listed_newest_first_and_searchable(): void
    {
        $first = $this->paidOrder();
        $this->travel(1)->minutes();
        $second = $this->paidOrder();

        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/orders?limit=1')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $second->uuid)
            ->assertJsonPath('data.items.0.pharmacy_name', 'Medplus Ikeja')
            ->assertJsonPath('data.items.0.patient_id', $second->patient->uuid)
            ->assertJsonPath('data.items.0.payment.status', 'paid');

        foreach ([$first->reference, $first->patient->phone, 'medplus'] as $q) {
            $this->actingAs($this->team)
                ->getJson('api/v1/portal/admin/orders?q=' . urlencode($q))
                ->assertJsonPath('data.total', $q === 'medplus' ? 2 : 1);
        }

        $this->actingAs($this->team)->getJson('api/v1/portal/admin/orders?status=open')->assertJsonPath('data.total', 2);
        $this->actingAs($this->team)->getJson('api/v1/portal/admin/orders?status=cancelled')->assertJsonPath('data.total', 0);
        $this->actingAs($this->team)->getJson('api/v1/portal/admin/orders?status=lost')->assertStatus(400);

        $this->actingAs($this->team)
            ->getJson("api/v1/portal/admin/orders/{$first->uuid}")
            ->assertOk()
            ->assertJsonPath('data.reference', $first->reference);
    }

    public function test_the_team_cancels_and_refunds_and_the_patient_reads_why(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/orders/{$order->uuid}/cancel", ['reason' => 'The pharmacy cannot fill this order.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation', ['by' => 'team', 'reason' => 'The pharmacy cannot fill this order.'])
            ->assertJsonPath('data.payment.status', 'refunded');

        $this->assertSame([$order->uuid], array_column($this->payments->refunds, 'target'));
        $this->assertSame(['order.cancelled'], $this->audit->actions());
        $this->assertSame('The 11med team cancelled your order', end($this->notifier->sent)['title']);

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/orders/{$order->uuid}/cancel", ['reason' => 'The pharmacy cannot fill this order.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_CANCELLED');
    }

    public function test_an_order_with_the_rider_is_not_cancelled(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['status' => 'ready'])->save();

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/orders/{$order->uuid}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ORDER_STARTED');

        $this->actingAs($this->team)
            ->postJson("api/v1/portal/admin/orders/{$order->uuid}/cancel", ['reason' => 'short'])
            ->assertStatus(400)
            ->assertJsonPath('error.field', 'reason');

        $this->actingAs($this->team)
            ->getJson('api/v1/portal/admin/orders/0193a0a0-0000-7000-8000-000000000000')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'ORDER_NOT_FOUND');
    }

    public function test_roles_and_permissions_are_refused(): void
    {
        $order = $this->paidOrder();

        $this->actingAs($this->teamMember(AdminPermission::OrdersView))
            ->postJson("api/v1/portal/admin/orders/{$order->uuid}/cancel", ['reason' => 'The patient asked us to cancel.'])
            ->assertForbidden();

        $this->actingAs($this->teamMember(AdminPermission::UsersView))
            ->getJson('api/v1/portal/admin/orders')
            ->assertForbidden();

        foreach ([User::factory()->app(AccountKind::Patient)->create(), User::factory()->portal(AccountKind::Pharmacy)->create()] as $user) {
            $this->actingAs($user)
                ->getJson('api/v1/portal/admin/orders')
                ->assertForbidden();
        }
    }

    private function paidOrder(): Order
    {
        $patient = $this->patient();
        $this->addToCart($patient, $this->drug($this->pharmacy));
        $id = $this->checkout($patient)[0]['id'];

        $order = Order::query()->where('uuid', $id)->firstOrFail();
        $this->payments->settle($order);

        return $order->refresh()->load('patient');
    }
}
