<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\Order;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function order(Business $business, string $plate, OrderStatus $status, int $total = 0, ?string $paidAt = null): Order
    {
        $type = VehicleType::withoutGlobalScopes()->firstOrCreate(['business_id' => $business->id, 'name' => 'Carro']);
        $vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $business->id, 'plate' => $plate],
            ['vehicle_type_id' => $type->id],
        );

        $order = new Order(['business_id' => $business->id, 'vehicle_id' => $vehicle->id, 'status' => $status, 'total' => $total]);
        $order->forceFill(['paid_at' => $paidAt])->save();

        return $order;
    }

    public function test_the_dashboard_shows_the_business_name_and_todays_counts(): void
    {
        $business = Business::create(['name' => 'Lavadero Uno']);
        $user = User::factory()->create(['business_id' => $business->id]);

        $this->order($business, 'AAA111', OrderStatus::Queued);
        $this->order($business, 'BBB222', OrderStatus::Queued);
        $this->order($business, 'CCC333', OrderStatus::Washing);
        $this->order($business, 'DDD444', OrderStatus::Ready);
        $this->order($business, 'EEE555', OrderStatus::Delivered, 30000, now()->toDateTimeString());
        $this->order($business, 'FFF666', OrderStatus::Delivered, 15000, now()->subDay()->toDateTimeString());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('business', 'Lavadero Uno')
                ->where('counts', ['queued' => 2, 'washing' => 1, 'ready' => 1, 'delivered_today' => 1])
                ->where('collected_today', 30000));
    }

    public function test_the_dashboard_ignores_other_businesses(): void
    {
        $mine = Business::create(['name' => 'Mine']);
        $other = Business::create(['name' => 'Other']);
        $user = User::factory()->create(['business_id' => $mine->id]);

        $this->order($other, 'ZZZ999', OrderStatus::Queued);
        $this->order($other, 'YYY888', OrderStatus::Delivered, 99000, now()->toDateTimeString());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts', ['queued' => 0, 'washing' => 0, 'ready' => 0, 'delivered_today' => 0])
                ->where('collected_today', 0));
    }

    public function test_users_without_a_business_get_an_empty_state_not_an_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('business', null));
    }
}
