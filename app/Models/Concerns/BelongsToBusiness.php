<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes a model to the authenticated user's business and stamps
 * business_id on creation. Without an authenticated user (console,
 * seeders, tests) no filter is applied.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $businessId = Auth::user()?->business_id;

            if ($businessId !== null) {
                $query->where($query->getModel()->getTable().'.business_id', $businessId);
            }
        });

        static::creating(function ($model) {
            if ($model->business_id === null) {
                $model->business_id = Auth::user()?->business_id;
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
