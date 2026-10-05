<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->char('location', 3)->nullable()->comment('IATA airport code where it happened');
            $table->string('flight_number', 10)->nullable();
            $table->unsignedInteger('pieces')->nullable();
            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->text('description')->nullable();
            $table->string('source', 20)->default('manual')->comment('manual | api:<driver>');
            $table->boolean('visible_to_customer')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_events');
    }
};
