<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'vehicle_type_id', 'customer_id', 'plate'];

    /** Plates are stored uppercase and without spaces so lookups are exact. */
    public static function normalizePlate(string $plate): string
    {
        return strtoupper(preg_replace('/\s+/', '', $plate));
    }

    protected function plate(): Attribute
    {
        return Attribute::set(fn (string $value) => self::normalizePlate($value));
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
