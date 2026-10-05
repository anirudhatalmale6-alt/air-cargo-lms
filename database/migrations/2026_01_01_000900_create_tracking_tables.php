<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Last known provider answer per AWB, so a refresh is one row read and the
        // public tracking page never depends on an airline API being up.
        Schema::create('tracking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('awb_number', 11)->unique();
            $table->string('driver', 40);
            $table->string('latest_status', 60)->nullable();
            $table->char('latest_location', 3)->nullable();
            $table->timestamp('latest_event_at')->nullable();
            $table->json('events')->comment('normalised TrackingEvent list');
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();
        });

        Schema::create('tracking_lookups', function (Blueprint $table) {
            $table->id();
            $table->char('awb_number', 11)->index();
            $table->string('driver', 40)->nullable();
            $table->boolean('found')->default(false);
            $table->boolean('from_cache')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_lookups');
        Schema::dropIfExists('tracking_snapshots');
    }
};
