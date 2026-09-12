<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->index(['batch_id', 'queue_type', 'status', 'processed_at', 'sequence_id'], 'idx_queue_metrics_processed_order');
        });

        Schema::table('queue_metric_attempts', function (Blueprint $table) {
            $table->index(['batch_id', 'queue_type', 'sequence_id'], 'idx_queue_metric_attempts_batch_queue_sequence');
        });

        Schema::table('queue_benchmark_runs', function (Blueprint $table) {
            $table->index(['queue_type', 'expected_messages'], 'idx_queue_benchmark_runs_queue_type_expected_messages');
        });
    }

    public function down(): void
    {
        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_queue_metrics_processed_order');
        });

        Schema::table('queue_metric_attempts', function (Blueprint $table) {
            $table->dropIndex('idx_queue_metric_attempts_batch_queue_sequence');
        });

        Schema::table('queue_benchmark_runs', function (Blueprint $table) {
            $table->dropIndex('idx_queue_benchmark_runs_queue_type_expected_messages');
        });
    }
};
