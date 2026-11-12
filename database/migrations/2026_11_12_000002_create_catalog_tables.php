<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('main');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Prices are whole Colombian pesos (no decimals).
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['service_id', 'vehicle_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('services');
        Schema::dropIfExists('vehicle_types');
    }
};
