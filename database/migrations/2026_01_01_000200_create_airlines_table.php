<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->char('iata_code', 2)->unique()->comment('e.g. LH, EK, AI');
            $table->char('icao_code', 3)->nullable();
            $table->char('awb_prefix', 3)->unique()->comment('3 digit IATA accounting prefix, e.g. 020');
            $table->string('name');
            $table->string('tracking_driver', 40)->default('internal')
                ->comment('key resolved by config/tracking.php drivers');
            $table->json('tracking_config')->nullable()
                ->comment('per airline overrides: endpoint, api key ref, field map');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
