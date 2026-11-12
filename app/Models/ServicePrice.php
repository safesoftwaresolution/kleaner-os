<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'service_id', 'vehicle_type_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }
}
