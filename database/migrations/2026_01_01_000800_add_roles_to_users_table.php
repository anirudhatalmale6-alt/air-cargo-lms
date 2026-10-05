<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('email')
                ->comment('admin | staff | customer');
            $table->foreignId('customer_id')->nullable()->after('role')
                ->constrained()->nullOnDelete()
                ->comment('set for role=customer, scopes everything they can see');
            $table->string('phone', 40)->nullable()->after('customer_id');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->timestamp('last_login_at')->nullable();

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['role', 'phone', 'is_active', 'last_login_at']);
        });
    }
};
