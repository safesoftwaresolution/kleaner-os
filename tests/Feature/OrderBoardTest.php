<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceKind;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderBoardTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $user;

    private VehicleType $car;

    private VehicleType $moto;

    private Service $wash;

    private Service $wax;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create(['name' => 'Lavadero Uno']);
        $this->user = User::factory()->create(['business_id' => $this->business->id]);

        $this->car = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Carro']);
        $this->moto = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Moto']);

        $this->wash = Service::create(['business_id' => $this->business->id, 'name' => 'Lavado', 'kind' => ServiceKind::Main]);
        $this->wax = Service::create(['business_id' => $this->business->id, 'name' => 'Cera', 'kind' => ServiceKind::Extra]);

        $this->price($this->wash, $this->car, 30000);
        $this->price($this->wash, $this->moto, 15000);
        $this->price($this->wax, $this->car, 10000); // no wax price for motos
    }

    private function price(Service $service, VehicleType $type, int $amount): void
    {
        $service->prices()->create([
            'business_id' => $this->business->id,
            'vehicle_type_id' => $type->id,
            'price' => $amount,
        ]);
    }

    private function order(string $plate = 'AAA111', ?Business $business = null): Order
    {
        $business ??= $this->business;
        $type = VehicleType::withoutGlobalScopes()->where('business_id', $business->id)->first()
            ?? VehicleType::create(['business_id' => $business->id, 'name' => 'Carro']);
        $vehicle = Vehicle::create([
            'business_id' => $business->id,
            'vehicle_type_id' => $type->id,
            'plate' => $plate,
        ]);

        return Order::create(['business_id' => $business->id, 'vehicle_id' => $vehicle->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_users_without_a_business_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/orders')->assertForbidden();
    }

    public function test_board_lists_only_orders_of_the_user_business(): void
    {
        $this->order('AAA111');
        $other = Business::create(['name' => 'Otro']);
        $this->order('ZZZ999', $other);

        $this->actingAs($this->user)
            ->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Index')
                ->has('orders', 1)
                ->where('orders.0.plate', 'AAA111'));
    }

    public function test_lookup_returns_known_vehicle_data(): void
    {
        $customer = Customer::create(['business_id' => $this->business->id, 'name' => 'Ana', 'phone' => '3001234567']);
        Vehicle::create([
            'business_id' => $this->business->id,
            'vehicle_type_id' => $this->car->id,
            'customer_id' => $customer->id,
            'plate' => 'ABC123',
        ]);

        $this->actingAs($this->user)
            ->getJson('/vehicles/lookup?plate=abc 123')
            ->assertOk()
            ->assertJson([
                'found' => true,
                'vehicle_type_id' => $this->car->id,
                'phone' => '3001234567',
                'name' => 'Ana',
            ]);
    }

    public function test_lookup_of_an_unknown_plate_is_not_found(): void
    {
        $this->actingAs($this->user)
            ->getJson('/vehicles/lookup?plate=NOPE00')
            ->assertOk()
            ->assertJson(['found' => false]);
    }

    public function test_lookup_does_not_leak_vehicles_of_other_businesses(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $this->order('SEC123', $other);

        $this->actingAs($this->user)
            ->getJson('/vehicles/lookup?plate=SEC123')
            ->assertJson(['found' => false]);
    }

    public function test_store_creates_vehicle_customer_and_priced_order(): void
    {
        $this->actingAs($this->user)
            ->post('/orders', [
                'plate' => ' abc 123 ',
                'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Ana',
                'customer_phone' => '3001234567',
                'service_id' => $this->wash->id,
                'extra_ids' => [$this->wax->id],
            ])
            ->assertRedirect();

        $order = Order::with('items', 'vehicle.customer')->sole();

        $this->assertSame(OrderStatus::Queued, $order->status);
        $this->assertSame(40000, $order->total);
        $this->assertSame([30000, 10000], $order->items->pluck('price')->all());
        $this->assertSame('ABC123', $order->vehicle->plate);
        $this->assertSame('3001234567', $order->vehicle->customer->phone);
        $this->assertSame($this->business->id, $order->business_id);
    }

    public function test_store_reuses_an_existing_vehicle(): void
    {
        $vehicle = Vehicle::create([
            'business_id' => $this->business->id,
            'vehicle_type_id' => $this->car->id,
            'plate' => 'ABC123',
        ]);

        $this->actingAs($this->user)->post('/orders', [
            'plate' => 'abc123',
            'vehicle_type_id' => $this->car->id,
            'service_id' => $this->wash->id,
        ])->assertRedirect();

        $this->assertSame(1, Vehicle::count());
        $this->assertSame($vehicle->id, Order::sole()->vehicle_id);
    }

    public function test_store_rejects_a_service_without_a_price_for_the_vehicle_type(): void
    {
        $this->actingAs($this->user)
            ->post('/orders', [
                'plate' => 'MOT123',
                'vehicle_type_id' => $this->moto->id,
                'service_id' => $this->wash->id,
                'extra_ids' => [$this->wax->id],
            ])
            ->assertSessionHasErrors('extra_ids');

        $this->assertSame(0, Order::count());
    }

    public function test_store_requires_plate_type_and_main_service(): void
    {
        $this->actingAs($this->user)
            ->post('/orders', [])
            ->assertSessionHasErrors(['plate', 'vehicle_type_id', 'service_id']);
    }

    public function test_status_advances_one_step_at_a_time(): void
    {
        $order = $this->order();

        $this->actingAs($this->user)
            ->patch("/orders/{$order->id}/status", ['status' => 'washing'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Washing, $order->fresh()->status);

        $this->actingAs($this->user)
            ->patch("/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
    }

    public function test_status_cannot_skip_steps(): void
    {
        $order = $this->order();

        $this->actingAs($this->user)
            ->patch("/orders/{$order->id}/status", ['status' => 'ready'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Queued, $order->fresh()->status);
    }

    public function test_delivering_requires_a_payment_method_and_stamps_paid_at(): void
    {
        $order = $this->order();
        $order->update(['status' => OrderStatus::Ready]);

        $this->actingAs($this->user)
            ->patch("/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);

        $this->actingAs($this->user)
            ->patch("/orders/{$order->id}/status", ['status' => 'delivered', 'payment_method' => 'cash'])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::Delivered, $fresh->status);
        $this->assertSame(PaymentMethod::Cash, $fresh->payment_method);
        $this->assertNotNull($fresh->paid_at);
    }

    private function readyOrderFor(?string $phone, ?string $name = null, string $plate = 'ABC123'): Order
    {
        $customer = $phone === null ? null : Customer::create([
            'business_id' => $this->business->id,
            'name' => $name,
            'phone' => $phone,
        ]);
        $vehicle = Vehicle::create([
            'business_id' => $this->business->id,
            'vehicle_type_id' => $this->car->id,
            'customer_id' => $customer?->id,
            'plate' => $plate,
        ]);

        return Order::create([
            'business_id' => $this->business->id,
            'vehicle_id' => $vehicle->id,
            'status' => OrderStatus::Ready,
        ]);
    }

    public function test_ready_orders_get_a_whatsapp_link_with_a_ready_message(): void
    {
        $this->readyOrderFor('300 123 4567', 'Ana');

        $message = 'Hola Ana, tu vehículo con placa ABC123 ya está listo en Lavadero Uno. ¡Te esperamos!';

        $this->actingAs($this->user)
            ->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.0.whatsapp_url', 'https://wa.me/573001234567?text='.rawurlencode($message)));
    }

    public function test_the_whatsapp_message_omits_the_name_when_unknown(): void
    {
        $this->readyOrderFor('3001234567');

        $message = 'Hola, tu vehículo con placa ABC123 ya está listo en Lavadero Uno. ¡Te esperamos!';

        $this->actingAs($this->user)
            ->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.0.whatsapp_url', 'https://wa.me/573001234567?text='.rawurlencode($message)));
    }

    public function test_there_is_no_whatsapp_link_without_a_valid_phone_or_before_the_order_is_ready(): void
    {
        $this->readyOrderFor(null, null, 'NOC111');
        $this->readyOrderFor('6012345678', null, 'LND000');
        $queued = $this->order('QUE111');
        $queued->vehicle->customer()->associate(
            Customer::create(['business_id' => $this->business->id, 'phone' => '3001234567'])
        )->save();

        $this->actingAs($this->user)
            ->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders', 3)
                ->where('orders.0.whatsapp_url', null)
                ->where('orders.1.whatsapp_url', null)
                ->where('orders.2.whatsapp_url', null));
    }

    public function test_orders_of_other_businesses_cannot_be_updated(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreign = $this->order('SEC123', $other);

        $this->actingAs($this->user)
            ->patch("/orders/{$foreign->id}/status", ['status' => 'washing'])
            ->assertNotFound();
    }
}
