<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_one_user_per_role_in_the_demo_business(): void
    {
        $this->seed();

        $business = Business::sole();

        foreach ([
            'test@example.com' => UserRole::Owner,
            'cajero@example.com' => UserRole::Cashier,
            'operador@example.com' => UserRole::Operator,
        ] as $email => $role) {
            $user = User::where('email', $email)->sole();

            $this->assertSame($role, $user->role, $email);
            $this->assertSame($business->id, $user->business_id, $email);
        }
    }
}
