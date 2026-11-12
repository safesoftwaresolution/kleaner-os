<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ServiceKind;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Whatsapp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $today = today();
        $businessName = $request->user()->business->name;

        $orders = Order::with(['vehicle.type', 'vehicle.customer', 'items.service'])
            ->where(fn ($q) => $q
                ->where('status', '!=', OrderStatus::Delivered->value)
                ->orWhere('paid_at', '>=', $today))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'whatsapp_url' => $order->status === OrderStatus::Ready
                    ? Whatsapp::link(
                        $order->vehicle->customer?->phone,
                        Whatsapp::vehicleReadyMessage($order->vehicle->customer?->name, $order->vehicle->plate, $businessName),
                    )
                    : null,
                'id' => $order->id,
                'plate' => $order->vehicle->plate,
                'vehicle_type' => $order->vehicle->type->name,
                'phone' => $order->vehicle->customer?->phone,
                'status' => $order->status->value,
                'total' => $order->total,
                'services' => $order->items->map(fn ($item) => $item->service->name)->all(),
                'payment_method' => $order->payment_method?->value,
                'created_at' => $order->created_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'vehicleTypes' => VehicleType::orderBy('id')->get(['id', 'name']),
            'services' => Service::with('prices')
                ->where('active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'kind' => $service->kind->value,
                    'prices' => $service->prices->pluck('price', 'vehicle_type_id'),
                ]),
            'summary' => [
                'orders_today' => Order::where('created_at', '>=', $today)->count(),
                'collected_today' => (int) Order::where('paid_at', '>=', $today)->sum('total'),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $customer = null;

            if ($request->filled('customer_phone')) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $request->string('customer_phone')->trim()->value()],
                    ['name' => $request->input('customer_name')],
                );

                if ($request->filled('customer_name') && $customer->name === null) {
                    $customer->update(['name' => $request->input('customer_name')]);
                }
            }

            $vehicle = Vehicle::firstOrNew(['plate' => Vehicle::normalizePlate($request->input('plate'))]);
            $vehicle->vehicle_type_id = $request->integer('vehicle_type_id');
            $vehicle->customer_id = $customer?->id ?? $vehicle->customer_id;
            $vehicle->save();

            $order = Order::create(['vehicle_id' => $vehicle->id]);

            $serviceIds = collect([$request->integer('service_id')])
                ->merge($request->input('extra_ids', []))
                ->map(fn ($id) => (int) $id);

            $prices = ServicePrice::where('vehicle_type_id', $vehicle->vehicle_type_id)
                ->whereIn('service_id', $serviceIds)
                ->pluck('price', 'service_id');

            foreach ($serviceIds as $serviceId) {
                $order->items()->create(['service_id' => $serviceId, 'price' => $prices[$serviceId]]);
            }

            $order->recalculateTotal();
        });

        return back();
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = OrderStatus::from($request->input('status'));

        $order->update([
            'status' => $status,
            ...($status === OrderStatus::Delivered
                ? ['payment_method' => $request->input('payment_method'), 'paid_at' => now()]
                : []),
        ]);

        return back();
    }
}
