<?php

namespace Database\Seeders;

use App\Enums\ServiceKind;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a demo car wash for local development.
     * Base prices (moto 15.000, carro 30.000) come from the first validation
     * interview; extras are placeholder values.
     */
    public function run(): void
    {
        $business = Business::create(['name' => 'Lavadero Demo']);

        foreach ([
            ['Dueño Demo', 'test@example.com', UserRole::Owner],
            ['Cajero Demo', 'cajero@example.com', UserRole::Cashier],
            ['Operador Demo', 'operador@example.com', UserRole::Operator],
        ] as [$name, $email, $role]) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
                'business_id' => $business->id,
                'role' => $role,
            ]);
        }

        $car = VehicleType::create(['business_id' => $business->id, 'name' => 'Carro']);
        $moto = VehicleType::create(['business_id' => $business->id, 'name' => 'Moto']);

        $wash = Service::create(['business_id' => $business->id, 'name' => 'Lavado básico', 'kind' => ServiceKind::Main]);
        $wax = Service::create(['business_id' => $business->id, 'name' => 'Cera', 'kind' => ServiceKind::Extra]);
        $vacuum = Service::create(['business_id' => $business->id, 'name' => 'Aspirado', 'kind' => ServiceKind::Extra]);

        foreach ([
            [$wash, $car, 30000],
            [$wash, $moto, 15000],
            [$wax, $car, 10000],
            [$vacuum, $car, 8000],
        ] as [$service, $type, $price]) {
            $service->prices()->create([
                'business_id' => $business->id,
                'vehicle_type_id' => $type->id,
                'price' => $price,
            ]);
        }
    }
}
