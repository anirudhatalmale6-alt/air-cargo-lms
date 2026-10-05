<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awbs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->char('prefix', 3);
            $table->char('serial', 8)->comment('7 running digits + mod 7 check digit');
            $table->char('number', 11)->unique()->comment('prefix + serial, digits only');
            $table->enum('status', ['available', 'assigned', 'used', 'void'])->default('available');
            $table->foreignId('batch_id')->nullable()->comment('stock batch the number came from');
            $table->date('received_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['prefix', 'serial']);
            $table->index(['airline_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('awbs');
    }
};
