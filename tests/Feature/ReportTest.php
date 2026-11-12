<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceKind;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $owner;

    private VehicleType $car;

    private VehicleType $moto;

    private Service $wash;

    private Service $wax;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::create(['name' => 'Lavadero Uno']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id]);
        $this->car = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Carro']);
        $this->moto = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Moto']);
        $this->wash = Service::create(['business_id' => $this->business->id, 'name' => 'Lavado', 'kind' => ServiceKind::Main]);
        $this->wax = Service::create(['business_id' => $this->business->id, 'name' => 'Cera', 'kind' => ServiceKind::Extra]);
    }

    /** @param list<array{0: Service, 1: int}> $items */
    private function makeOrder(
        string $plate,
        VehicleType $type,
        string $createdAt,
        array $items,
        ?string $paidAt = null,
        ?PaymentMethod $method = null,
        ?Business $business = null,
    ): Order {
        $business ??= $this->business;

        $vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
            ['business_id' => $business->id, 'plate' => $plate],
            ['vehicle_type_id' => $type->id],
        );

        $order = new Order([
            'business_id' => $business->id,
            'vehicle_id' => $vehicle->id,
            'status' => $paidAt ? OrderStatus::Delivered : OrderStatus::Queued,
            'payment_method' => $method,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt, 'paid_at' => $paidAt])->save();

        foreach ($items as [$service, $price]) {
            $order->items()->create(['business_id' => $business->id, 'service_id' => $service->id, 'price' => $price]);
        }
        $order->recalculateTotal();

        return $order;
    }

    /** Seeds the scenario the main report test asserts against (day = 2026-11-10). */
    private function seedScenario(): void
    {
        // Previous day: same car as order A, delivered and paid that day.
        $this->makeOrder('ABC123', $this->car, '2026-11-09 09:00:00', [[$this->wash, 30000]], '2026-11-09 10:00:00', PaymentMethod::Transfer);
        // A: car, wash + wax, delivered and paid in cash on the day.
        $this->makeOrder('ABC123', $this->car, '2026-11-10 10:00:00', [[$this->wash, 30000], [$this->wax, 10000]], '2026-11-10 12:00:00', PaymentMethod::Cash);
        // B: moto, wash, still queued.
        $this->makeOrder('MOT111', $this->moto, '2026-11-10 11:00:00', [[$this->wash, 15000]]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_only_owners_can_view_reports(): void
    {
        foreach ([UserRole::Cashier, UserRole::Operator] as $role) {
            $user = User::factory()->create(['business_id' => $this->business->id, 'role' => $role]);

            $this->actingAs($user)->get('/reports')->assertForbidden();
        }
    }

    public function test_the_report_summarizes_a_day(): void
    {
        $this->seedScenario();

        $this->actingAs($this->owner)
            ->get('/reports?date=2026-11-10')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('date', '2026-11-10')
                ->where('report.vehicles', 2)
                ->where('report.returning_vehicles', 1)
                ->where('report.collected', 40000)
                ->where('report.pending_count', 1)
                ->where('report.pending_total', 15000)
                ->has('report.collected_by_method', 1)
                ->where('report.collected_by_method.0.method', 'cash')
                ->where('report.collected_by_method.0.count', 1)
                ->where('report.collected_by_method.0.total', 40000)
                ->has('report.by_vehicle_type', 2)
                ->where('report.by_vehicle_type.0', ['name' => 'Carro', 'count' => 1, 'total' => 40000])
                ->where('report.by_vehicle_type.1', ['name' => 'Moto', 'count' => 1, 'total' => 15000])
                ->has('report.by_service', 2)
                ->where('report.by_service.0', ['name' => 'Lavado', 'count' => 2, 'total' => 45000])
                ->where('report.by_service.1', ['name' => 'Cera', 'count' => 1, 'total' => 10000]));
    }

    public function test_cash_collected_counts_on_the_day_it_is_paid_not_created(): void
    {
        // Created the day before, paid on the day in the morning.
        $this->makeOrder('LATE11', $this->car, '2026-11-09 20:00:00', [[$this->wash, 30000]], '2026-11-10 08:00:00', PaymentMethod::Transfer);

        $this->actingAs($this->owner)
            ->get('/reports?date=2026-11-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.vehicles', 0)
                ->where('report.collected', 30000));
    }

    public function test_the_report_excludes_other_businesses(): void
    {
        $this->seedScenario();
        $other = Business::create(['name' => 'Otro']);
        $otherType = VehicleType::create(['business_id' => $other->id, 'name' => 'Carro']);
        $otherService = Service::create(['business_id' => $other->id, 'name' => 'Ajeno', 'kind' => ServiceKind::Main]);
        $this->makeOrder('SEC123', $otherType, '2026-11-10 10:00:00', [[$otherService, 99000]], '2026-11-10 11:00:00', PaymentMethod::Cash, $other);

        $this->actingAs($this->owner)
            ->get('/reports?date=2026-11-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.vehicles', 2)
                ->where('report.collected', 40000)
                ->has('report.by_service', 2));
    }

    public function test_the_daily_series_covers_the_last_seven_days_ending_on_the_date(): void
    {
        $this->seedScenario();

        $this->actingAs($this->owner)
            ->get('/reports?date=2026-11-10')
            ->assertInertia(fn (Assert $page) => $page
                ->has('daily', 7)
                ->where('daily.0.date', '2026-11-04')
                ->where('daily.5', ['date' => '2026-11-09', 'vehicles' => 1, 'collected' => 30000])
                ->where('daily.6', ['date' => '2026-11-10', 'vehicles' => 2, 'collected' => 40000]));
    }

    public function test_the_date_defaults_to_today(): void
    {
        $this->makeOrder('NOW111', $this->car, now()->toDateTimeString(), [[$this->wash, 30000]]);

        $this->actingAs($this->owner)
            ->get('/reports')
            ->assertInertia(fn (Assert $page) => $page
                ->where('date', today()->toDateString())
                ->where('report.vehicles', 1));
    }

    public function test_invalid_or_future_dates_are_rejected(): void
    {
        foreach (['not-a-date', '2026-13-45', today()->addDay()->toDateString()] as $bad) {
            $this->actingAs($this->owner)
                ->get('/reports?date='.$bad)
                ->assertSessionHasErrors('date');
        }
    }

    public function test_an_empty_day_returns_zeros(): void
    {
        $this->actingAs($this->owner)
            ->get('/reports?date=2026-11-10')
            ->assertInertia(fn (Assert $page) => $page
                ->where('report.vehicles', 0)
                ->where('report.collected', 0)
                ->where('report.pending_total', 0)
                ->has('report.by_service', 0)
                ->has('report.collected_by_method', 0));
    }
}
