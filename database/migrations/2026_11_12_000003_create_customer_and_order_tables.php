<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'phone']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate');
            $table->timestamps();

            $table->unique(['business_id', 'plate']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued');
            $table->string('payment_method')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            // Price charged at the time of the order; never derived from service_prices.
            $table->unsignedInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('customers');
    }
};
