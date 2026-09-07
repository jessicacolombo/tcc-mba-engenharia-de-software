<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('queue_benchmark_runs', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();
            $table->string('queue_type');
            $table->unsignedInteger('expected_messages');
            $table->string('status')->default('dispatching');
            $table->double('started_at');
            $table->double('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['queue_type', 'status']);
        });

        Schema::table('queue_metric_attempts', function (Blueprint $table) {
            $table->unique(
                ['batch_id', 'queue_type', 'sequence_id', 'attempt'],
                'qma_batch_type_seq_attempt_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('queue_metric_attempts', function (Blueprint $table) {
            $table->dropUnique('qma_batch_type_seq_attempt_unique');
        });

        Schema::dropIfExists('queue_benchmark_runs');
    }
};
