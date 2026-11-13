<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceKind;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Today" is frozen at 2026-11-13. The default 30-day window is 2026-10-15..2026-11-13
 * and the previous window is 2026-09-15..2026-10-14.
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private VehicleType $car;

    private VehicleType $moto;

    private Service $wash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00:00'));

        $this->business = Business::create(['name' => 'Lavadero Uno']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => UserRole::Owner]);
        $this->car = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Carro']);
        $this->moto = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Moto']);
        $this->wash = Service::create(['business_id' => $this->business->id, 'name' => 'Lavado', 'kind' => ServiceKind::Main]);
    }

    private function vehicle(string $plate, VehicleType $type, ?string $customerName = null, ?Business $business = null): Vehicle
    {
        $business ??= $this->business;
        $customer = $customerName ? Customer::create(['business_id' => $business->id, 'name' => $customerName, 'phone' => '3001234567']) : null;

        return Vehicle::create([
            'business_id' => $business->id,
            'vehicle_type_id' => $type->id,
            'customer_id' => $customer?->id,
            'plate' => $plate,
        ]);
    }

    private function order(Vehicle $vehicle, string $createdAt, int $total, bool $paid, ?Service $service = null): Order
    {
        $service ??= $this->wash;

        $order = new Order([
            'business_id' => $vehicle->business_id,
            'vehicle_id' => $vehicle->id,
            'status' => $paid ? OrderStatus::Delivered : OrderStatus::Queued,
            'payment_method' => $paid ? PaymentMethod::Cash : null,
            'total' => $total,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt, 'paid_at' => $paid ? $createdAt : null])->save();
        $order->items()->create(['business_id' => $vehicle->business_id, 'service_id' => $service->id, 'price' => $total]);

        return $order;
    }

    private function seedScenario(): void
    {
        $v1 = $this->vehicle('AAA111', $this->car, 'Ana');
        $v2 = $this->vehicle('MOT111', $this->moto);
        $v3 = $this->vehicle('CAM333', $this->car);
        $v4 = $this->vehicle('OLD444', $this->car);

        $this->order($v1, '2026-09-01 10:00:00', 30000, true);   // before both windows: makes V1 an existing customer
        $this->order($v1, '2026-11-05 10:00:00', 30000, true);
        $this->order($v1, '2026-11-12 14:00:00', 40000, true);
        $this->order($v2, '2026-11-10 09:00:00', 15000, true);   // first ever visit of V2
        $this->order($v2, '2026-11-12 09:30:00', 15000, false);  // repeat, not paid yet
        $this->order($v3, '2026-11-13 10:00:00', 30000, false);  // first ever visit of V3, today
        $this->order($v4, '2026-10-01 11:00:00', 20000, true);   // previous window only
    }

    private function analytics(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner)->get('/dashboard'.$query);
    }

    public function test_kpis_compare_the_window_with_the_previous_one(): void
    {
        $this->seedScenario();

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('analytics.range', 30)
            ->where('analytics.from', '2026-10-15')
            ->where('analytics.to', '2026-11-13')
            ->where('analytics.kpis.vehicles', ['value' => 5, 'previous' => 1, 'delta' => 400])
            ->where('analytics.kpis.collected', ['value' => 85000, 'previous' => 20000, 'delta' => 325])
            ->where('analytics.kpis.new_vehicles', ['value' => 2, 'previous' => 1, 'delta' => 100])
            ->where('analytics.kpis.returning_vehicles', ['value' => 2, 'previous' => 0, 'delta' => null]));
    }

    public function test_the_daily_series_splits_first_visits_from_repeat_visits(): void
    {
        $this->seedScenario();

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->has('analytics.daily', 30)
            ->where('analytics.daily.0.date', '2026-10-15')
            ->where('analytics.daily.29', ['date' => '2026-11-13', 'vehicles' => 1, 'collected' => 0, 'first_visits' => 1, 'repeat_visits' => 0])
            ->where('analytics.daily.28', ['date' => '2026-11-12', 'vehicles' => 2, 'collected' => 40000, 'first_visits' => 0, 'repeat_visits' => 2])
            ->where('analytics.daily.26', ['date' => '2026-11-10', 'vehicles' => 1, 'collected' => 15000, 'first_visits' => 1, 'repeat_visits' => 0])
            ->where('analytics.daily.21', ['date' => '2026-11-05', 'vehicles' => 1, 'collected' => 30000, 'first_visits' => 0, 'repeat_visits' => 1])
            ->where('analytics.daily.1', ['date' => '2026-10-16', 'vehicles' => 0, 'collected' => 0, 'first_visits' => 0, 'repeat_visits' => 0]));
    }

    public function test_hourly_counts_cover_all_24_hours(): void
    {
        $this->seedScenario();

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->has('analytics.hourly', 24)
            ->where('analytics.hourly.9', 2)
            ->where('analytics.hourly.10', 2)
            ->where('analytics.hourly.14', 1)
            ->where('analytics.hourly.0', 0));
    }

    public function test_breakdowns_by_vehicle_type_and_service_are_ranked_by_total(): void
    {
        $this->seedScenario();

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->has('analytics.by_type', 2)
            ->where('analytics.by_type.0', ['name' => 'Carro', 'count' => 3, 'total' => 100000])
            ->where('analytics.by_type.1', ['name' => 'Moto', 'count' => 2, 'total' => 30000])
            ->has('analytics.by_service', 1)
            ->where('analytics.by_service.0', ['name' => 'Lavado', 'count' => 5, 'total' => 130000]));
    }

    public function test_many_services_fold_the_tail_into_other(): void
    {
        $vehicle = $this->vehicle('AAA111', $this->car);

        foreach (range(1, 8) as $n) {
            $service = Service::create(['business_id' => $this->business->id, 'name' => "Servicio {$n}", 'kind' => ServiceKind::Extra]);
            $this->order($vehicle, '2026-11-10 10:00:00', $n * 1000, true, $service);
        }

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->has('analytics.by_service', 6)
            ->where('analytics.by_service.0.name', 'Servicio 8')
            ->where('analytics.by_service.5', ['name' => 'Otros', 'count' => 3, 'total' => 6000]));
    }

    public function test_recurring_customers_are_ranked_by_visits_in_the_window(): void
    {
        $this->seedScenario();

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->has('analytics.recurring', 2)
            ->where('analytics.recurring.0.plate', 'AAA111')
            ->where('analytics.recurring.0.customer_name', 'Ana')
            ->where('analytics.recurring.0.visits', 2)
            ->where('analytics.recurring.0.total_visits', 3)
            ->where('analytics.recurring.0.spent', 70000)
            ->where('analytics.recurring.1.plate', 'MOT111')
            ->where('analytics.recurring.1.visits', 2)
            ->where('analytics.recurring.1.total_visits', 2)
            ->where('analytics.recurring.1.spent', 15000));
    }

    public function test_a_shorter_range_changes_the_window_and_the_comparison(): void
    {
        $this->seedScenario();

        $this->analytics('?range=7')->assertInertia(fn (Assert $page) => $page
            ->where('analytics.range', 7)
            ->where('analytics.from', '2026-11-07')
            ->has('analytics.daily', 7)
            ->where('analytics.kpis.vehicles', ['value' => 4, 'previous' => 1, 'delta' => 300])
            ->where('analytics.kpis.collected', ['value' => 55000, 'previous' => 30000, 'delta' => 83])
            ->where('analytics.kpis.new_vehicles', ['value' => 2, 'previous' => 0, 'delta' => null]));
    }

    public function test_only_7_30_and_90_day_ranges_are_accepted(): void
    {
        foreach (['0', '14', '365', 'abc', '30.5'] as $bad) {
            $this->analytics('?range='.$bad)->assertSessionHasErrors('range');
        }

        $this->analytics('?range=90')->assertInertia(fn (Assert $page) => $page->has('analytics.daily', 90));
    }

    public function test_the_analytics_ignore_other_businesses(): void
    {
        $this->seedScenario();

        $other = Business::create(['name' => 'Otro']);
        $otherType = VehicleType::create(['business_id' => $other->id, 'name' => 'Carro']);
        $foreign = $this->vehicle('SEC999', $otherType, 'Ajeno', $other);
        $otherService = Service::create(['business_id' => $other->id, 'name' => 'Ajeno', 'kind' => ServiceKind::Main]);
        $this->order($foreign, '2026-11-12 10:00:00', 99000, true, $otherService);

        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->where('analytics.kpis.vehicles.value', 5)
            ->where('analytics.kpis.collected.value', 85000)
            ->has('analytics.by_service', 1)
            ->has('analytics.recurring', 2));
    }

    public function test_an_empty_business_gets_zeros_and_no_deltas(): void
    {
        $this->analytics()->assertInertia(fn (Assert $page) => $page
            ->where('analytics.kpis.vehicles', ['value' => 0, 'previous' => 0, 'delta' => null])
            ->where('analytics.kpis.collected', ['value' => 0, 'previous' => 0, 'delta' => null])
            ->has('analytics.daily', 30)
            ->has('analytics.hourly', 24)
            ->has('analytics.by_type', 0)
            ->has('analytics.by_service', 0)
            ->has('analytics.recurring', 0));
    }

    public function test_only_the_owner_gets_the_analytics(): void
    {
        $this->seedScenario();

        foreach ([UserRole::Cashier, UserRole::Operator] as $role) {
            $user = User::factory()->create(['business_id' => $this->business->id, 'role' => $role]);

            $this->actingAs($user)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->where('analytics', null)
                    ->has('counts'));
        }
    }

    public function test_users_without_a_business_get_no_analytics(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('business', null)->where('analytics', null));
    }
}
