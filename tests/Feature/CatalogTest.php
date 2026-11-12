<?php

namespace Tests\Feature;

use App\Enums\ServiceKind;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
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
        $this->wash->prices()->create(['business_id' => $this->business->id, 'vehicle_type_id' => $this->car->id, 'price' => 30000]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/catalog')->assertRedirect('/login');
    }

    public function test_only_owners_can_manage_the_catalog(): void
    {
        foreach ([UserRole::Cashier, UserRole::Operator] as $role) {
            $user = User::factory()->create(['business_id' => $this->business->id, 'role' => $role]);

            $this->actingAs($user)->get('/catalog')->assertForbidden();
            $this->actingAs($user)->post('/catalog/vehicle-types', ['name' => 'Bus'])->assertForbidden();
            $this->actingAs($user)->put("/catalog/services/{$this->wash->id}/prices", ['prices' => []])->assertForbidden();
        }

        $this->assertSame(2, VehicleType::count());
    }

    public function test_index_lists_vehicle_types_and_services_with_prices(): void
    {
        $this->actingAs($this->owner)
            ->get('/catalog')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Index')
                ->has('vehicleTypes', 2)
                ->has('services', 1)
                ->where('services.0.name', 'Lavado')
                ->where("services.0.prices.{$this->car->id}", 30000));
    }

    public function test_owner_can_create_a_vehicle_type(): void
    {
        $this->actingAs($this->owner)
            ->post('/catalog/vehicle-types', ['name' => 'Camioneta'])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_types', ['business_id' => $this->business->id, 'name' => 'Camioneta']);
    }

    public function test_vehicle_type_names_are_unique_per_business_only(): void
    {
        $this->actingAs($this->owner)
            ->post('/catalog/vehicle-types', ['name' => 'Carro'])
            ->assertSessionHasErrors('name');

        $other = Business::create(['name' => 'Otro']);
        $otherOwner = User::factory()->create(['business_id' => $other->id]);

        $this->actingAs($otherOwner)
            ->post('/catalog/vehicle-types', ['name' => 'Carro'])
            ->assertRedirect();

        $this->assertSame(1, VehicleType::withoutGlobalScopes()->where('business_id', $other->id)->count());
    }

    public function test_owner_can_create_a_service(): void
    {
        $this->actingAs($this->owner)
            ->post('/catalog/services', ['name' => 'Cera', 'kind' => 'extra'])
            ->assertRedirect();

        $service = Service::where('name', 'Cera')->sole();
        $this->assertSame(ServiceKind::Extra, $service->kind);
        $this->assertTrue($service->active);
    }

    public function test_service_requires_a_name_and_a_valid_kind(): void
    {
        $this->actingAs($this->owner)
            ->post('/catalog/services', ['name' => '', 'kind' => 'otro'])
            ->assertSessionHasErrors(['name', 'kind']);
    }

    public function test_prices_are_set_updated_and_removed(): void
    {
        $this->actingAs($this->owner)
            ->put("/catalog/services/{$this->wash->id}/prices", [
                'prices' => [$this->car->id => 32000, $this->moto->id => 15000],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$this->car->id => 32000, $this->moto->id => 15000],
            $this->wash->prices()->pluck('price', 'vehicle_type_id')->all(),
        );

        $this->actingAs($this->owner)
            ->put("/catalog/services/{$this->wash->id}/prices", [
                'prices' => [$this->car->id => 32000, $this->moto->id => null],
            ])
            ->assertRedirect();

        $this->assertSame([$this->car->id => 32000], $this->wash->prices()->pluck('price', 'vehicle_type_id')->all());
    }

    public function test_prices_must_be_positive_whole_numbers(): void
    {
        foreach (['abc', -5, 0, 12.5] as $invalid) {
            $this->actingAs($this->owner)
                ->put("/catalog/services/{$this->wash->id}/prices", ['prices' => [$this->car->id => $invalid]])
                ->assertSessionHasErrors("prices.{$this->car->id}");
        }

        $this->assertSame(30000, $this->wash->prices()->sole()->price);
    }

    public function test_prices_cannot_reference_vehicle_types_of_other_businesses(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreign = VehicleType::create(['business_id' => $other->id, 'name' => 'Bus']);

        $this->actingAs($this->owner)
            ->put("/catalog/services/{$this->wash->id}/prices", ['prices' => [$foreign->id => 50000]])
            ->assertSessionHasErrors();

        $this->assertSame(1, $this->wash->prices()->count());
    }

    public function test_services_of_other_businesses_cannot_be_edited(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreign = Service::create(['business_id' => $other->id, 'name' => 'Ajeno', 'kind' => ServiceKind::Main]);

        $this->actingAs($this->owner)
            ->patch("/catalog/services/{$foreign->id}", ['active' => false])
            ->assertNotFound();

        $this->assertTrue($foreign->fresh()->active);
    }

    public function test_price_keys_must_be_plain_vehicle_type_ids(): void
    {
        foreach (["{$this->car->id}abc", "0{$this->car->id}", ' '.$this->car->id] as $badKey) {
            $this->actingAs($this->owner)
                ->put("/catalog/services/{$this->wash->id}/prices", ['prices' => [$badKey => 5000]])
                ->assertSessionHasErrors('prices');
        }

        $this->assertSame(30000, $this->wash->prices()->sole()->price);
    }

    public function test_non_owners_cannot_create_or_edit_services(): void
    {
        foreach ([UserRole::Cashier, UserRole::Operator] as $role) {
            $user = User::factory()->create(['business_id' => $this->business->id, 'role' => $role]);

            $this->actingAs($user)->post('/catalog/services', ['name' => 'Cera', 'kind' => 'extra'])->assertForbidden();
            $this->actingAs($user)->patch("/catalog/services/{$this->wash->id}", ['active' => false])->assertForbidden();
        }

        $this->assertSame(1, Service::count());
        $this->assertTrue($this->wash->fresh()->active);
    }

    public function test_prices_of_services_of_other_businesses_cannot_be_replaced(): void
    {
        $other = Business::create(['name' => 'Otro']);
        $foreignType = VehicleType::create(['business_id' => $other->id, 'name' => 'Carro']);
        $foreign = Service::create(['business_id' => $other->id, 'name' => 'Ajeno', 'kind' => ServiceKind::Main]);
        $foreign->prices()->create(['business_id' => $other->id, 'vehicle_type_id' => $foreignType->id, 'price' => 20000]);

        $this->actingAs($this->owner)
            ->put("/catalog/services/{$foreign->id}/prices", ['prices' => [$this->car->id => 1000]])
            ->assertNotFound();

        // The business global scope hides foreign rows from the authenticated user, so bypass it to inspect them.
        $this->assertSame(20000, $foreign->prices()->withoutGlobalScopes()->sole()->price);
    }

    public function test_duplicate_names_are_rejected_by_the_database_too(): void
    {
        $this->expectException(QueryException::class);

        VehicleType::create(['business_id' => $this->business->id, 'name' => 'Carro']);
    }

    public function test_deactivated_services_cannot_be_used_in_new_orders(): void
    {
        $this->actingAs($this->owner)
            ->patch("/catalog/services/{$this->wash->id}", ['active' => false])
            ->assertRedirect();

        $this->assertFalse($this->wash->fresh()->active);

        $this->actingAs($this->owner)
            ->post('/orders', [
                'plate' => 'ABC123',
                'vehicle_type_id' => $this->car->id,
                'service_id' => $this->wash->id,
            ])
            ->assertSessionHasErrors('service_id');

        $this->assertSame(0, Vehicle::count());
    }
}
