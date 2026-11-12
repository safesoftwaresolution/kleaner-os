<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    protected $fillable = ['name', 'phone', 'timezone'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
