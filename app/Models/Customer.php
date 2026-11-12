<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'name', 'phone'];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }
}
