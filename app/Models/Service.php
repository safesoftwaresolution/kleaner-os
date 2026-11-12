<?php

namespace App\Models;

use App\Enums\ServiceKind;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'name', 'kind', 'active'];

    protected function casts(): array
    {
        return [
            'kind' => ServiceKind::class,
            'active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }
}
