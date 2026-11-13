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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VehicleDirectoryTest extends TestCase
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

        $this->business = Business::create(['name' => 'Lavadero Uno']);
        $this->owner = User::factory()->create(['business_id' => $this->business->id, 'role' => UserRole::Owner]);
        $this->car = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Carro']);
        $this->moto = VehicleType::create(['business_id' => $this->business->id, 'name' => 'Moto']);
        $this->wash = Service::create(['business_id' => $this->business->id, 'name' => 'Lavado', 'kind' => ServiceKind::Main]);
    }

    private function vehicle(string $plate, ?Customer $customer = null, ?VehicleType $type = null, ?Business $business = null): Vehicle
    {
        $business ??= $this->business;
        $type ??= $business->is($this->business)
            ? $this->car
            : VehicleType::create(['business_id' => $business->id, 'name' => 'Carro']);

        return Vehicle::create([
            'business_id' => $business->id,
            'vehicle_type_id' => $type->id,
            'customer_id' => $customer?->id,
            'plate' => $plate,
        ]);
    }

    private function customer(?string $name, ?string $phone, ?Business $business = null): Customer
    {
        return Customer::create(['business_id' => ($business ?? $this->business)->id, 'name' => $name, 'phone' => $phone]);
    }

    private function visit(Vehicle $vehicle, string $createdAt, int $total, bool $delivered = true, ?Service $service = null): Order
    {
        $service ??= $this->wash;

        $order = new Order([
            'business_id' => $vehicle->business_id,
            'vehicle_id' => $vehicle->id,
            'status' => $delivered ? OrderStatus::Delivered : OrderStatus::Queued,
            'payment_method' => $delivered ? PaymentMethod::Cash : null,
            'total' => $total,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt, 'paid_at' => $delivered ? $createdAt : null])->save();
        $order->items()->create(['business_id' => $vehicle->business_id, 'service_id' => $service->id, 'price' => $total]);

        return $order;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/vehicles')->assertRedirect('/login');
    }

    public function test_owners_and_cashiers_can_use_the_directory_but_operators_cannot(): void
    {
        $vehicle = $this->vehicle('AAA111');

        foreach ([UserRole::Owner, UserRole::Cashier] as $role) {
            $user = User::factory()->create(['business_id' => $this->business->id, 'role' => $role]);
            $this->actingAs($user)->get('/vehicles')->assertOk();
            $this->actingAs($user)->get("/vehicles/{$vehicle->id}")->assertOk();
        }

        $operator = User::factory()->create(['business_id' => $this->business->id, 'role' => UserRole::Operator]);
        $this->actingAs($operator)->get('/vehicles')->assertForbidden();
        $this->actingAs($operator)->get("/vehicles/{$vehicle->id}")->assertForbidden();
        $this->actingAs($operator)->put("/vehicles/{$vehicle->id}", [
            'plate' => 'ZZZ999', 'vehicle_type_id' => $this->car->id,
        ])->assertForbidden();

        $this->assertSame('AAA111', $vehicle->fresh()->plate);
    }

    public function test_the_list_shows_visits_total_spent_and_last_visit_per_vehicle(): void
    {
        $ana = $this->customer('Ana', '3001234567');
        $a = $this->vehicle('AAA111', $ana);
        $this->visit($a, '2026-11-01 10:00:00', 30000);
        $this->visit($a, '2026-11-08 10:00:00', 40000);
        $this->visit($a, '2026-11-10 10:00:00', 15000, delivered: false); // not paid yet: counts as a visit, not as spent

        $this->actingAs($this->owner)
            ->get('/vehicles')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vehicles/Index')
                ->where('vehicles.total', 1)
                ->where('vehicles.data.0.plate', 'AAA111')
                ->where('vehicles.data.0.type', 'Carro')
                ->where('vehicles.data.0.customer_name', 'Ana')
                ->where('vehicles.data.0.customer_phone', '3001234567')
                ->where('vehicles.data.0.visits', 3)
                ->where('vehicles.data.0.total_spent', 70000));
    }

    public function test_the_list_exposes_a_whatsapp_number_only_for_valid_mobile_phones(): void
    {
        $this->vehicle('AAA111', $this->customer('Ana', '300 123 4567'));
        $this->vehicle('BBB222', $this->customer('Fijo', '6012345678'));
        $this->vehicle('CCC333');

        $this->actingAs($this->owner)
            ->get('/vehicles')
            ->assertInertia(fn (Assert $page) => $page
                ->where('business', 'Lavadero Uno')
                ->where('vehicles.data.0.plate', 'AAA111')
                ->where('vehicles.data.0.whatsapp_number', '573001234567')
                ->where('vehicles.data.1.plate', 'BBB222')
                ->where('vehicles.data.1.whatsapp_number', null)
                ->where('vehicles.data.2.plate', 'CCC333')
                ->where('vehicles.data.2.whatsapp_number', null));
    }

    public function test_the_detail_exposes_the_whatsapp_number_and_the_business_name(): void
    {
        $with = $this->vehicle('AAA111', $this->customer('Ana', '+57 300 123 4567'));
        $without = $this->vehicle('BBB222');

        $this->actingAs($this->owner)
            ->get("/vehicles/{$with->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicle.whatsapp_number', '573001234567')
                ->where('business', 'Lavadero Uno'));

        $this->actingAs($this->owner)
            ->get("/vehicles/{$without->id}")
            ->assertInertia(fn (Assert $page) => $page->where('vehicle.whatsapp_number', null));
    }

    public function test_the_list_is_ordered_by_most_recent_visit(): void
    {
        $old = $this->vehicle('OLD111');
        $new = $this->vehicle('NEW222');
        $this->visit($old, '2026-10-01 10:00:00', 30000);
        $this->visit($new, '2026-11-09 10:00:00', 30000);

        $this->actingAs($this->owner)
            ->get('/vehicles')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicles.data.0.plate', 'NEW222')
                ->where('vehicles.data.1.plate', 'OLD111'));
    }

    public function test_the_list_only_shows_vehicles_of_the_users_business(): void
    {
        $this->vehicle('AAA111');
        $other = Business::create(['name' => 'Otro']);
        $this->vehicle('ZZZ999', null, null, $other);

        $this->actingAs($this->owner)
            ->get('/vehicles')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicles.total', 1)
                ->where('vehicles.data.0.plate', 'AAA111'));
    }

    public function test_the_list_is_paginated(): void
    {
        foreach (range(1, 25) as $n) {
            $this->vehicle(sprintf('PLT%03d', $n));
        }

        $this->actingAs($this->owner)
            ->get('/vehicles')
            ->assertInertia(fn (Assert $page) => $page
                ->where('vehicles.total', 25)
                ->has('vehicles.data', 20));

        $this->actingAs($this->owner)
            ->get('/vehicles?page=2')
            ->assertInertia(fn (Assert $page) => $page->has('vehicles.data', 5));
    }

    public function test_search_matches_plate_name_and_phone_ignoring_case_and_spacing(): void
    {
        $this->vehicle('ABC123', $this->customer('Ana Pérez', '300 123 4567'));
        $this->vehicle('XYZ789', $this->customer('Carlos', '3109876543'));

        foreach ([
            'abc 12' => 'ABC123',
            'ana' => 'ABC123',
            '300123' => 'ABC123',
            '300 123' => 'ABC123',
            'carlos' => 'XYZ789',
            '310-987' => 'XYZ789',
        ] as $term => $plate) {
            $term = (string) $term; // numeric array keys become integers in PHP
            $this->actingAs($this->owner)
                ->get('/vehicles?q='.urlencode($term))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('vehicles.total', 1)
                    ->where('vehicles.data.0.plate', $plate)
                    ->where('filters.q', $term));
        }
    }

    public function test_search_treats_wildcard_characters_literally(): void
    {
        $this->vehicle('ABC123', $this->customer('Ana', '3001234567'));

        foreach (['%', '_', 'A%3'] as $term) {
            $this->actingAs($this->owner)
                ->get('/vehicles?q='.urlencode($term))
                ->assertInertia(fn (Assert $page) => $page->where('vehicles.total', 0));
        }
    }

    public function test_search_does_not_reach_other_businesses(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $this->vehicle('SEC123', $this->customer('Secreto', '3200000000', $other), null, $other);

        $this->actingAs($this->owner)
            ->get('/vehicles?q=secreto')
            ->assertInertia(fn (Assert $page) => $page->where('vehicles.total', 0));
    }

    public function test_the_detail_shows_the_history_and_stats(): void
    {
        $vehicle = $this->vehicle('AAA111', $this->customer('Ana', '3001234567'));
        $this->visit($vehicle, '2026-11-01 10:00:00', 30000);
        $this->visit($vehicle, '2026-11-08 10:00:00', 40000);
        $this->visit($vehicle, '2026-11-10 10:00:00', 15000, delivered: false);

        $this->actingAs($this->owner)
            ->get("/vehicles/{$vehicle->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vehicles/Show')
                ->where('vehicle.plate', 'AAA111')
                ->where('vehicle.customer_name', 'Ana')
                ->where('stats.visits', 3)
                ->where('stats.delivered', 2)
                ->where('stats.total_spent', 70000)
                ->where('stats.average', 35000)
                ->has('history', 3)
                ->where('history.0.total', 15000)
                ->where('history.0.status', 'queued')
                ->where('history.0.services', ['Lavado'])
                ->where('history.2.total', 30000)
                ->where('history.2.payment_method', 'cash'));
    }

    public function test_the_detail_of_a_vehicle_without_visits_has_zero_stats(): void
    {
        $vehicle = $this->vehicle('AAA111');

        $this->actingAs($this->owner)
            ->get("/vehicles/{$vehicle->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.visits', 0)
                ->where('stats.total_spent', 0)
                ->where('stats.average', 0)
                ->has('history', 0));
    }

    public function test_vehicles_of_other_businesses_are_not_found(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreign = $this->vehicle('SEC123', null, null, $other);

        $this->actingAs($this->owner)->get("/vehicles/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->owner)->put("/vehicles/{$foreign->id}", [
            'plate' => 'HACK11', 'vehicle_type_id' => $this->car->id,
        ])->assertNotFound();

        $this->assertSame('SEC123', $foreign->fresh()->plate);
    }

    public function test_the_plate_and_type_can_be_corrected(): void
    {
        $vehicle = $this->vehicle('ABC12O'); // typo: letter O instead of zero

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", ['plate' => ' abc 120 ', 'vehicle_type_id' => $this->moto->id])
            ->assertRedirect();

        $fresh = $vehicle->fresh();
        $this->assertSame('ABC120', $fresh->plate);
        $this->assertSame($this->moto->id, $fresh->vehicle_type_id);
    }

    public function test_a_plate_cannot_duplicate_another_vehicle_of_the_same_business(): void
    {
        $this->vehicle('AAA111');
        $other = $this->vehicle('BBB222');

        $this->actingAs($this->owner)
            ->put("/vehicles/{$other->id}", ['plate' => 'aaa 111', 'vehicle_type_id' => $this->car->id])
            ->assertSessionHasErrors('plate');

        $this->assertSame('BBB222', $other->fresh()->plate);
    }

    public function test_the_same_plate_is_allowed_in_another_business_and_keeping_your_own_is_allowed(): void
    {
        $theirs = Business::create(['name' => 'Otro']);
        $this->vehicle('AAA111', null, null, $theirs);
        $mine = $this->vehicle('BBB222');

        $this->actingAs($this->owner)
            ->put("/vehicles/{$mine->id}", ['plate' => 'AAA111', 'vehicle_type_id' => $this->car->id])
            ->assertRedirect();
        $this->assertSame('AAA111', $mine->fresh()->plate);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$mine->id}", ['plate' => 'aaa111', 'vehicle_type_id' => $this->car->id])
            ->assertRedirect();
    }

    public function test_the_vehicle_type_must_belong_to_the_business(): void
    {
        $vehicle = $this->vehicle('AAA111');
        $other = Business::create(['name' => 'Otro']);
        $foreignType = VehicleType::create(['business_id' => $other->id, 'name' => 'Bus']);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", ['plate' => 'AAA111', 'vehicle_type_id' => $foreignType->id])
            ->assertSessionHasErrors('vehicle_type_id');

        $this->assertSame($this->car->id, $vehicle->fresh()->vehicle_type_id);
    }

    public function test_the_vehicle_type_must_be_a_single_id_not_an_array(): void
    {
        $vehicle = $this->vehicle('AAA111', null, $this->moto);

        // An array passes the exists rule but would be cast to id 1 when saved.
        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", ['plate' => 'AAA111', 'vehicle_type_id' => [$this->moto->id]])
            ->assertSessionHasErrors('vehicle_type_id');

        $this->assertSame($this->moto->id, $vehicle->fresh()->vehicle_type_id);
    }

    public function test_a_phone_that_belongs_to_another_business_is_never_linked(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreign = $this->customer('Ajeno', '3001234567', $other);
        $vehicle = $this->vehicle('AAA111');

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Ana', 'customer_phone' => '3001234567',
            ])
            ->assertRedirect();

        $linked = $vehicle->fresh()->customer;
        $this->assertNotSame($foreign->id, $linked->id);
        $this->assertSame($this->business->id, $linked->business_id);
        $this->assertSame('Ajeno', $foreign->fresh()->name);
    }

    public function test_contact_data_can_be_added_to_a_vehicle_without_customer(): void
    {
        $vehicle = $this->vehicle('AAA111');

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Ana', 'customer_phone' => '3001234567',
            ])
            ->assertRedirect();

        $customer = $vehicle->fresh()->customer;
        $this->assertSame('Ana', $customer->name);
        $this->assertSame('3001234567', $customer->phone);
    }

    public function test_changing_the_phone_links_the_existing_customer_with_that_phone(): void
    {
        $ana = $this->customer('Ana', '3001234567');
        $pedro = $this->customer('Pedro', '3109876543');
        $vehicle = $this->vehicle('AAA111', $ana);
        $this->vehicle('BBB222', $ana); // another vehicle sharing Ana

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Pedro', 'customer_phone' => '3109876543',
            ])
            ->assertRedirect();

        $this->assertSame($pedro->id, $vehicle->fresh()->customer_id);
        $this->assertSame(2, Customer::count()); // no duplicate customer created
        $this->assertSame('3001234567', $ana->fresh()->phone); // the shared customer is untouched
        $this->assertSame('Ana', $ana->fresh()->name);
    }

    public function test_the_name_can_be_corrected_keeping_the_phone(): void
    {
        $ana = $this->customer('Anna', '3001234567'); // typo in the name
        $vehicle = $this->vehicle('AAA111', $ana);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Ana', 'customer_phone' => '3001234567',
            ])
            ->assertRedirect();

        $this->assertSame($ana->id, $vehicle->fresh()->customer_id);
        $this->assertSame('Ana', $ana->fresh()->name);
        $this->assertSame(1, Customer::count());
    }

    public function test_a_new_phone_creates_a_customer_without_changing_the_old_one(): void
    {
        $ana = $this->customer('Ana', '3001234567');
        $vehicle = $this->vehicle('AAA111', $ana);
        $sibling = $this->vehicle('BBB222', $ana);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id,
                'customer_name' => 'Ana', 'customer_phone' => '3205550000',
            ])
            ->assertRedirect();

        $this->assertSame('3205550000', $vehicle->fresh()->customer->phone);
        $this->assertSame($ana->id, $sibling->fresh()->customer_id);
        $this->assertSame('3001234567', $sibling->fresh()->customer->phone);
    }

    public function test_a_name_only_updates_a_customer_that_has_no_phone(): void
    {
        $nameless = $this->customer(null, null);
        $vehicle = $this->vehicle('AAA111', $nameless);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id, 'customer_name' => 'Ana',
            ])
            ->assertRedirect();

        $this->assertSame($nameless->id, $vehicle->fresh()->customer_id);
        $this->assertSame('Ana', $nameless->fresh()->name);
    }

    public function test_clearing_name_and_phone_detaches_the_customer_without_deleting_it(): void
    {
        $ana = $this->customer('Ana', '3001234567');
        $vehicle = $this->vehicle('AAA111', $ana);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => 'AAA111', 'vehicle_type_id' => $this->car->id, 'customer_name' => '', 'customer_phone' => '',
            ])
            ->assertRedirect();

        $this->assertNull($vehicle->fresh()->customer_id);
        $this->assertNotNull($ana->fresh());
    }

    public function test_update_validates_its_inputs(): void
    {
        $vehicle = $this->vehicle('AAA111');

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [])
            ->assertSessionHasErrors(['plate', 'vehicle_type_id']);

        $this->actingAs($this->owner)
            ->put("/vehicles/{$vehicle->id}", [
                'plate' => str_repeat('A', 11), 'vehicle_type_id' => $this->car->id,
                'customer_name' => str_repeat('n', 101), 'customer_phone' => str_repeat('3', 21),
            ])
            ->assertSessionHasErrors(['plate', 'customer_name', 'customer_phone']);
    }
}
