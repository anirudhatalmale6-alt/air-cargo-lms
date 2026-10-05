<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique()->comment('internal job number, e.g. EXP-2026-00042');
            $table->enum('direction', ['export', 'import']);

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('airline_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('awb_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->char('awb_number', 11)->nullable()->index()
                ->comment('denormalised for fast public tracking lookups');
            $table->string('hawb_number', 30)->nullable()->index();

            $table->char('origin', 3)->comment('IATA airport code');
            $table->char('destination', 3);
            $table->string('flight_number', 10)->nullable();
            $table->date('flight_date')->nullable();
            $table->date('eta')->nullable();

            $table->unsignedInteger('pieces')->default(1);
            $table->decimal('gross_weight_kg', 10, 2)->default(0);
            $table->decimal('volume_cbm', 10, 3)->nullable();
            $table->decimal('chargeable_weight_kg', 10, 2)->default(0);
            $table->string('commodity')->nullable();
            $table->string('nature_of_goods')->nullable();
            $table->enum('incoterm', ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'])->nullable();
            $table->enum('payment_term', ['prepaid', 'collect'])->default('prepaid');
            $table->boolean('is_dangerous_goods')->default(false);
            $table->string('shipper_name')->nullable();
            $table->string('consignee_name')->nullable();

            $table->string('current_status', 40)->default('draft')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->decimal('charges_total', 14, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['direction', 'current_status']);
            $table->index(['customer_id', 'created_at']);
            $table->index('flight_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
