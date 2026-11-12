<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Request validation already rejects duplicates; these indexes close the race between two simultaneous requests. */
    public function up(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->unique(['business_id', 'name']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'name']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'name']);
        });
    }
};
