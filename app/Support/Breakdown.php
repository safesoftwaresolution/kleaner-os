<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Groups items into ranked rows of {name|key, count, total}, shared by the report and the dashboard. */
class Breakdown
{
    /**
     * Sorted by total (desc), then name (asc).
     *
     * @param  Collection<int, mixed>  $items
     * @return list<array<string, int|string>>
     */
    public static function of(Collection $items, callable $label, callable $amount, string $key = 'name'): array
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

    /**
     * Keeps the first (limit - 1) rows and folds the rest into one "Otros" row,
     * so a chart never needs more categories than it can tell apart.
     *
     * @param  list<array<string, int|string>>  $rows
     * @return list<array<string, int|string>>
     */
    public static function withOther(array $rows, int $limit): array
    {
        if (count($rows) <= $limit) {
            return $rows;
        }

        $head = array_slice($rows, 0, $limit - 1);
        $tail = array_slice($rows, $limit - 1);

        $head[] = [
            'name' => 'Otros',
            'count' => (int) array_sum(array_column($tail, 'count')),
            'total' => (int) array_sum(array_column($tail, 'total')),
        ];

        return $head;
    }
}
