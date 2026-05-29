<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 255)->nullable()->unique()->after('balance_after');
            $table->json('metadata')->nullable()->after('idempotency_key');

            // Add indexes for performance
            $table->index(['user_id', 'idempotency_key'], 'idx_user_idempotency');
            $table->index(['type', 'created_at'], 'idx_created_type');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'metadata']);
            $table->dropIndex('idx_user_idempotency');
            $table->dropIndex('idx_created_type');
        });
    }
};
