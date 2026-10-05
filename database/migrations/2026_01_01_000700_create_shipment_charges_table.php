<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('basis', ['flat', 'per_kg', 'per_piece', 'per_awb'])->default('flat');
            $table->decimal('default_rate', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipment_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->enum('basis', ['flat', 'per_kg', 'per_piece', 'per_awb'])->default('flat');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('rate', 12, 4)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->enum('side', ['payable', 'receivable'])->default('receivable');
            $table->boolean('is_taxable')->default(false);
            $table->timestamps();

            $table->index(['shipment_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_charges');
        Schema::dropIfExists('charge_types');
    }
};
