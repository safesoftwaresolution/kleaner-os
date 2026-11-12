<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily report. Definitions:
 * - vehicles: orders created that day
 * - collected: orders paid (delivered) that day, whenever they were created
 * - pending: orders created that day and not delivered yet
 *
 * Volumes per car wash are small (tens of orders a day), so aggregation is
 * done in memory over scoped Eloquent queries instead of raw SQL.
 */
class ReportController extends Controller
{
    private const DAILY_WINDOW = 7;

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $start = isset($data['date']) ? Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay() : today();
        $end = $start->copy()->addDay();

        $created = Order::with(['vehicle.type', 'items.service'])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->get();

        $paid = Order::where('paid_at', '>=', $start)->where('paid_at', '<', $end)->get();

        $earlierVehicleIds = Order::whereIn('vehicle_id', $created->pluck('vehicle_id')->unique())
            ->where('created_at', '<', $start)
            ->distinct()
            ->pluck('vehicle_id');

        $pending = $created->filter(fn (Order $order) => $order->status !== OrderStatus::Delivered);

        return Inertia::render('Reports/Index', [
            'date' => $start->toDateString(),
            'report' => [
                'vehicles' => $created->count(),
                'returning_vehicles' => $created->filter(fn (Order $o) => $earlierVehicleIds->contains($o->vehicle_id))->count(),
                'collected' => (int) $paid->sum('total'),
                'pending_count' => $pending->count(),
                'pending_total' => (int) $pending->sum('total'),
                'collected_by_method' => $this->breakdown(
                    $paid,
                    fn (Order $o) => $o->payment_method?->value ?? 'other',
                    fn (Order $o) => $o->total,
                    key: 'method',
                ),
                'by_vehicle_type' => $this->breakdown(
                    $created,
                    fn (Order $o) => $o->vehicle->type->name,
                    fn (Order $o) => $o->total,
                ),
                'by_service' => $this->breakdown(
                    $created->flatMap->items,
                    fn ($item) => $item->service->name,
                    fn ($item) => $item->price,
                ),
            ],
            'daily' => $this->daily($start),
        ]);
    }

    /**
     * Groups items into [{name|method, count, total}] sorted by total (desc), then name.
     *
     * @param  Collection<int, mixed>  $items
     * @return list<array<string, int|string>>
     */
    private function breakdown(Collection $items, callable $label, callable $amount, string $key = 'name'): array
    {
        return $items
            ->groupBy($label)
            ->map(fn (Collection $group, string $name) => [
                $key => $name,
                'count' => $group->count(),
                'total' => (int) $group->sum($amount),
            ])
            ->sortBy([['total', 'desc'], [$key, 'asc']])
            ->values()
            ->all();
    }

    /** @return list<array{date: string, vehicles: int, collected: int}> */
    private function daily(Carbon $date): array
    {
        $from = $date->copy()->subDays(self::DAILY_WINDOW - 1)->startOfDay();
        $to = $date->copy()->addDay();

        $created = Order::where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->get(['id', 'created_at'])
            ->countBy(fn (Order $o) => $o->created_at->toDateString());

        $collected = Order::where('paid_at', '>=', $from)->where('paid_at', '<', $to)
            ->get(['id', 'paid_at', 'total'])
            ->groupBy(fn (Order $o) => $o->paid_at->toDateString())
            ->map(fn (Collection $orders) => (int) $orders->sum('total'));

        return collect(range(0, self::DAILY_WINDOW - 1))
            ->map(function (int $offset) use ($from, $created, $collected) {
                $day = $from->copy()->addDays($offset)->toDateString();

                return [
                    'date' => $day,
                    'vehicles' => (int) ($created[$day] ?? 0),
                    'collected' => (int) ($collected[$day] ?? 0),
                ];
            })
            ->all();
    }
}
