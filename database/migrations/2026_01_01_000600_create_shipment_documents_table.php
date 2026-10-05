<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->comment('awb_copy, invoice, packing_list, coo, dgd, mawb, delivery_order, other');
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable()->comment('sha256 of the stored bytes');
            $table->boolean('visible_to_customer')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_documents');
    }
};
