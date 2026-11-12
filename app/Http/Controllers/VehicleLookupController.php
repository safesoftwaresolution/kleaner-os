<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $plate = Vehicle::normalizePlate((string) $request->query('plate', ''));

        $vehicle = $plate === '' ? null : Vehicle::with('customer')->where('plate', $plate)->first();

        if (! $vehicle) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'vehicle_type_id' => $vehicle->vehicle_type_id,
            'name' => $vehicle->customer?->name,
            'phone' => $vehicle->customer?->phone,
        ]);
    }
}
