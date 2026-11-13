<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Directory of registered vehicles and their customers. Vehicles and
 * customers are created automatically when an order is registered; this
 * controller lets staff find them, review their history and fix their data.
 *
 * "Spent" counts delivered (paid) orders only, like the daily report's cash basis.
 */
class VehicleController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        $query = Vehicle::with(['type', 'customer'])
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn (Builder $q) => $q->where('status', OrderStatus::Delivered->value)], 'total')
            ->withMax('orders as last_visit', 'created_at');

        if ($term !== '') {
            $this->applySearch($query, $term);
        }

        $vehicles = $query
            ->orderByDesc('last_visit')
            ->orderBy('plate')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'type' => $vehicle->type->name,
                'customer_name' => $vehicle->customer?->name,
                'customer_phone' => $vehicle->customer?->phone,
                'visits' => (int) $vehicle->orders_count,
                'total_spent' => (int) $vehicle->total_spent,
                'last_visit' => $vehicle->last_visit ? Carbon::parse($vehicle->last_visit)->toIso8601String() : null,
            ]);

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
            'filters' => ['q' => $term],
        ]);
    }

    public function show(Vehicle $vehicle): Response
    {
        $vehicle->load(['type', 'customer']);

        $orders = $vehicle->orders()
            ->with('items.service')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $delivered = $orders->filter(fn (Order $order) => $order->status === OrderStatus::Delivered);
        $spent = (int) $delivered->sum('total');

        return Inertia::render('Vehicles/Show', [
            'vehicle' => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'vehicle_type_id' => $vehicle->vehicle_type_id,
                'type' => $vehicle->type->name,
                'customer_name' => $vehicle->customer?->name,
                'customer_phone' => $vehicle->customer?->phone,
            ],
            'vehicleTypes' => VehicleType::orderBy('id')->get(['id', 'name']),
            'stats' => [
                'visits' => $orders->count(),
                'delivered' => $delivered->count(),
                'total_spent' => $spent,
                'average' => $delivered->isEmpty() ? 0 : (int) round($spent / $delivered->count()),
                'first_visit' => $orders->last()?->created_at->toIso8601String(),
                'last_visit' => $orders->first()?->created_at->toIso8601String(),
            ],
            'history' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'created_at' => $order->created_at->toIso8601String(),
                'status' => $order->status->value,
                'total' => $order->total,
                'payment_method' => $order->payment_method?->value,
                'services' => $order->items->map(fn ($item) => $item->service->name)->values()->all(),
            ])->values(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        DB::transaction(function () use ($request, $vehicle) {
            $customer = $this->resolveCustomer($vehicle, $request->input('customer_name'), $request->input('customer_phone'));

            $vehicle->update([
                'plate' => $request->input('plate'),
                'vehicle_type_id' => $request->integer('vehicle_type_id'),
                'customer_id' => $customer?->id,
            ]);
        });

        return back();
    }

    /**
     * Case-insensitive "contains" search over plate, customer name and, when the
     * term looks like a number, the customer's phone. Uses INSTR instead of LIKE so
     * "%" and "_" are matched literally.
     */
    private function applySearch(Builder $query, string $term): void
    {
        $needle = mb_strtolower($term);
        $plate = Vehicle::normalizePlate($term);
        $digits = preg_replace('/\D+/', '', $term);
        $looksLikeNumber = $digits !== '' && ! preg_match('/\p{L}/u', $term);

        $query->where(function (Builder $q) use ($needle, $plate, $digits, $looksLikeNumber) {
            $q->whereRaw('INSTR(vehicles.plate, ?) > 0', [$plate])
                ->orWhereHas('customer', function (Builder $customer) use ($needle, $digits, $looksLikeNumber) {
                    $customer->whereRaw('INSTR(LOWER(customers.name), ?) > 0', [$needle]);

                    if ($looksLikeNumber) {
                        $customer->orWhereRaw(
                            "INSTR(REPLACE(REPLACE(REPLACE(customers.phone, ' ', ''), '-', ''), '+', ''), ?) > 0",
                            [$digits],
                        );
                    }
                });
        });
    }

    /**
     * Editing a vehicle's contact never rewrites another vehicle's customer:
     * a phone that already belongs to a customer links to that customer, a new
     * phone creates one, and empty name and phone detach the vehicle.
     */
    private function resolveCustomer(Vehicle $vehicle, ?string $name, ?string $phone): ?Customer
    {
        $name = filled($name) ? trim($name) : null;
        $phone = filled($phone) ? trim($phone) : null;

        if ($name === null && $phone === null) {
            return null;
        }

        if ($phone !== null) {
            $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $name]);

            // The vehicle's own customer can be renamed; someone else's name is only filled when empty.
            if ($name !== null && ($customer->id === $vehicle->customer_id || $customer->name === null)) {
                $customer->update(['name' => $name]);
            }

            return $customer;
        }

        $current = $vehicle->customer;

        if ($current && $current->phone === null) {
            $current->update(['name' => $name]);

            return $current;
        }

        return Customer::create(['name' => $name]);
    }
}
