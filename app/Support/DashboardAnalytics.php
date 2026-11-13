<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Numbers behind the owner's dashboard for the last N days, compared with the N days before.
 *
 * Definitions (kept in sync with the labels shown on the dashboard):
 * - vehicles: orders created in the window
 * - collected: orders paid (delivered) in the window, whenever they were created
 * - new vehicles: vehicles whose first-ever order falls in the window
 * - returning vehicles: vehicles with at least one repeat visit (an order after their first) in the window
 * - spent (recurring list): totals of delivered orders created in the window
 *
 * Volumes per car wash are small, so aggregation runs in memory over business-scoped queries.
 */
class DashboardAnalytics
{
    public const RANGES = [7, 30, 90];

    private const TOP_SERVICES = 6;

    private const TOP_RECURRING = 5;

    /** @return array<string, mixed> */
    public static function compute(CarbonImmutable $today, int $days): array
    {
        $end = $today->startOfDay()->addDay();
        $start = $end->subDays($days);
        $previousStart = $start->subDays($days);

        /** @var Collection<int, Carbon> $firstVisit vehicle id => time of its first-ever order */
        $firstVisit = Order::selectRaw('vehicle_id, MIN(created_at) as first_at')
            ->groupBy('vehicle_id')
            ->pluck('first_at', 'vehicle_id')
            ->map(fn ($at) => Carbon::parse($at));

        $isRepeat = fn (Order $order) => $order->created_at->gt($firstVisit[$order->vehicle_id]);

        $current = Order::with(['vehicle.type', 'vehicle.customer', 'items.service'])
            ->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->get();
        $previous = Order::where('created_at', '>=', $previousStart)->where('created_at', '<', $start)
            ->get(['id', 'vehicle_id', 'created_at']);
        $paid = Order::where('paid_at', '>=', $start)->where('paid_at', '<', $end)->get(['id', 'total', 'paid_at']);
        $paidBefore = Order::where('paid_at', '>=', $previousStart)->where('paid_at', '<', $start)->get(['id', 'total', 'paid_at']);

        $newIn = fn ($from, $to) => $firstVisit->filter(fn (Carbon $at) => $at->gte($from) && $at->lt($to))->count();
        $returningIn = fn (Collection $orders) => $orders->filter($isRepeat)->pluck('vehicle_id')->unique()->count();

        return [
            'range' => $days,
            'from' => $start->toDateString(),
            'to' => $today->toDateString(),
            'kpis' => [
                'vehicles' => self::kpi($current->count(), $previous->count()),
                'collected' => self::kpi((int) $paid->sum('total'), (int) $paidBefore->sum('total')),
                'new_vehicles' => self::kpi($newIn($start, $end), $newIn($previousStart, $start)),
                'returning_vehicles' => self::kpi($returningIn($current), $returningIn($previous)),
            ],
            'daily' => self::daily($start, $days, $current, $paid, $isRepeat),
            'hourly' => self::hourly($current),
            'by_type' => Breakdown::of($current, fn (Order $o) => $o->vehicle->type->name, fn (Order $o) => $o->total),
            'by_service' => Breakdown::withOther(
                Breakdown::of($current->flatMap->items, fn ($item) => $item->service->name, fn ($item) => $item->price),
                self::TOP_SERVICES,
            ),
            'recurring' => self::recurring($current),
        ];
    }

    /** @return array{value: int, previous: int, delta: int|null} delta is a rounded percentage, null without a baseline */
    private static function kpi(int $value, int $previous): array
    {
        return [
            'value' => $value,
            'previous' => $previous,
            'delta' => $previous === 0 ? null : (int) round(($value - $previous) / $previous * 100),
        ];
    }

    /**
     * @param  Collection<int, Order>  $current
     * @param  Collection<int, Order>  $paid
     * @return list<array{date: string, vehicles: int, collected: int, first_visits: int, repeat_visits: int}>
     */
    private static function daily(CarbonImmutable $start, int $days, Collection $current, Collection $paid, callable $isRepeat): array
    {
        $byDay = $current->groupBy(fn (Order $o) => $o->created_at->toDateString());
        $collected = $paid->groupBy(fn (Order $o) => $o->paid_at->toDateString())
            ->map(fn (Collection $orders) => (int) $orders->sum('total'));

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $byDay, $collected, $isRepeat) {
            $date = $start->addDays($offset)->toDateString();
            $orders = $byDay->get($date, collect());
            $repeat = $orders->filter($isRepeat)->count();

            return [
                'date' => $date,
                'vehicles' => $orders->count(),
                'collected' => (int) $collected->get($date, 0),
                'first_visits' => $orders->count() - $repeat,
                'repeat_visits' => $repeat,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Order>  $current
     * @return list<int> orders per hour of the day, 0..23
     */
    private static function hourly(Collection $current): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($current as $order) {
            $hours[$order->created_at->hour]++;
        }

        return $hours;
    }

    /**
     * Vehicles that came in the window and have at least two visits overall, most active first.
     *
     * @param  Collection<int, Order>  $current
     * @return list<array<string, mixed>>
     */
    private static function recurring(Collection $current): array
    {
        $totalVisits = Order::whereIn('vehicle_id', $current->pluck('vehicle_id')->unique())
            ->selectRaw('vehicle_id, COUNT(*) as visits')
            ->groupBy('vehicle_id')
            ->pluck('visits', 'vehicle_id');

        return $current->groupBy('vehicle_id')
            ->map(function (Collection $orders, int $vehicleId) use ($totalVisits) {
                $vehicle = $orders->first()->vehicle;

                return [
                    'vehicle_id' => $vehicleId,
                    'plate' => $vehicle->plate,
                    'customer_name' => $vehicle->customer?->name,
                    'visits' => $orders->count(),
                    'total_visits' => (int) $totalVisits->get($vehicleId, 0),
                    'spent' => (int) $orders->filter(fn (Order $o) => $o->status === OrderStatus::Delivered)->sum('total'),
                    'last_visit' => $orders->max('created_at')->toIso8601String(),
                ];
            })
            ->filter(fn (array $row) => $row['total_visits'] >= 2)
            ->sortBy([['visits', 'desc'], ['spent', 'desc'], ['plate', 'asc']])
            ->take(self::TOP_RECURRING)
            ->values()
            ->all();
    }
}
