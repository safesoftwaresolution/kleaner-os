<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Without a business the BelongsToBusiness scope applies no filter, so never query orders here:
        // it would expose every business's data to an account that is not attached to one.
        if (! $user->business_id) {
            return Inertia::render('Dashboard', [
                'business' => null,
                'counts' => $this->counts([], 0),
                'collected_today' => 0,
            ]);
        }

        $open = Order::whereIn('status', [OrderStatus::Queued->value, OrderStatus::Washing->value, OrderStatus::Ready->value])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $paidToday = Order::where('paid_at', '>=', today());

        return Inertia::render('Dashboard', [
            'business' => $user->business->name,
            'counts' => $this->counts($open, (clone $paidToday)->count()),
            'collected_today' => (int) $paidToday->sum('total'),
        ]);
    }

    /**
     * @param  array<string, int>  $open
     * @return array{queued: int, washing: int, ready: int, delivered_today: int}
     */
    private function counts(array $open, int $deliveredToday): array
    {
        return [
            'queued' => (int) ($open[OrderStatus::Queued->value] ?? 0),
            'washing' => (int) ($open[OrderStatus::Washing->value] ?? 0),
            'ready' => (int) ($open[OrderStatus::Ready->value] ?? 0),
            'delivered_today' => $deliveredToday,
        ];
    }
}
