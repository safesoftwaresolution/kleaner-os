<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ServiceKind;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusiness(string $name = 'Lavadero Uno'): Business
    {
        return Business::create(['name' => $name]);
    }

    private function makeVehicle(Business $business, string $plate, string $typeName = 'Carro'): Vehicle
    {
        $type = VehicleType::create(['business_id' => $business->id, 'name' => $typeName]);

        return Vehicle::create([
            'business_id' => $business->id,
            'vehicle_type_id' => $type->id,
            'plate' => $plate,
        ]);
    }

    public function test_plates_are_normalized_to_uppercase_without_spaces(): void
    {
        $vehicle = $this->makeVehicle($this->makeBusiness(), ' abc 123 ');

        $this->assertSame('ABC123', $vehicle->plate);
    }

    public function test_same_plate_is_allowed_in_different_businesses_but_not_twice_in_one(): void
    {
        $a = $this->makeBusiness('A');
        $b = $this->makeBusiness('B');

        $this->makeVehicle($a, 'ABC123');
        $this->makeVehicle($b, 'ABC123');

        $this->expectException(QueryException::class);
        $this->makeVehicle($a, 'abc123');
    }

    public function test_only_records_of_the_authenticated_user_business_are_visible(): void
    {
        $mine = $this->makeBusiness('Mine');
        $other = $this->makeBusiness('Other');
        $this->makeVehicle($mine, 'AAA111');
        $this->makeVehicle($other, 'BBB222');

        $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

        $this->assertSame(['AAA111'], Vehicle::pluck('plate')->all());
    }

    public function test_new_records_get_the_authenticated_user_business(): void
    {
        $mine = $this->makeBusiness();
        $this->actingAs(User::factory()->create(['business_id' => $mine->id]));

        $customer = Customer::create(['name' => 'Ana', 'phone' => '3001234567']);

        $this->assertSame($mine->id, $customer->business_id);
    }

    public function test_order_items_keep_the_charged_price_when_the_service_price_changes(): void
    {
        $business = $this->makeBusiness();
        $vehicle = $this->makeVehicle($business, 'AAA111');
        $service = Service::create([
            'business_id' => $business->id,
            'name' => 'Lavado básico',
            'kind' => ServiceKind::Main,
        ]);
        $service->prices()->create([
            'business_id' => $business->id,
            'vehicle_type_id' => $vehicle->vehicle_type_id,
            'price' => 30000,
        ]);

        $order = Order::create(['business_id' => $business->id, 'vehicle_id' => $vehicle->id]);
        $order->items()->create([
            'business_id' => $business->id,
            'service_id' => $service->id,
            'price' => 30000,
        ]);

        $service->prices()->update(['price' => 35000]);

        $this->assertSame(30000, $order->items()->first()->price);
    }

    public function test_order_total_is_recalculated_from_its_items(): void
    {
        $business = $this->makeBusiness();
        $vehicle = $this->makeVehicle($business, 'AAA111');
        $main = Service::create(['business_id' => $business->id, 'name' => 'Lavado', 'kind' => ServiceKind::Main]);
        $extra = Service::create(['business_id' => $business->id, 'name' => 'Cera', 'kind' => ServiceKind::Extra]);

        $order = Order::create(['business_id' => $business->id, 'vehicle_id' => $vehicle->id]);
        $order->items()->create(['business_id' => $business->id, 'service_id' => $main->id, 'price' => 30000]);
        $order->items()->create(['business_id' => $business->id, 'service_id' => $extra->id, 'price' => 10000]);

        $order->recalculateTotal();

        $this->assertSame(40000, $order->fresh()->total);
    }

    public function test_orders_start_queued_and_can_change_status(): void
    {
        $business = $this->makeBusiness();
        $vehicle = $this->makeVehicle($business, 'AAA111');

        $order = Order::create(['business_id' => $business->id, 'vehicle_id' => $vehicle->id]);

        $this->assertSame(OrderStatus::Queued, $order->fresh()->status);

        $order->update(['status' => OrderStatus::Washing]);

        $this->assertSame(OrderStatus::Washing, $order->fresh()->status);
    }
}
