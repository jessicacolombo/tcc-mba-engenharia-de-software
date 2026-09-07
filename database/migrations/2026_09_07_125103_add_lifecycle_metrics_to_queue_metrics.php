<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->string('status')->default('dispatched')->after('latency_ms');
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->unsignedSmallInteger('processing_count')->default(0)->after('attempts');
            $table->unsignedSmallInteger('duplicate_count')->default(0)->after('processing_count');
            $table->double('processed_at')->nullable()->after('received_timestamp');
            $table->double('failed_at')->nullable()->after('processed_at');
            $table->text('error_message')->nullable()->after('failed_at');

            $table->index(['batch_id', 'queue_type', 'status']);
            $table->unique(['batch_id', 'queue_type', 'sequence_id']);
        });

        Schema::create('queue_metric_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->index();
            $table->string('queue_type');
            $table->unsignedInteger('sequence_id');
            $table->unsignedSmallInteger('attempt');
            $table->string('status');
            $table->double('started_at');
            $table->double('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'queue_type', 'sequence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metric_attempts');

        Schema::table('queue_metrics', function (Blueprint $table) {
            $table->dropUnique(['batch_id', 'queue_type', 'sequence_id']);
            $table->dropIndex(['batch_id', 'queue_type', 'status']);
            $table->dropColumn([
                'status',
                'attempts',
                'processing_count',
                'duplicate_count',
                'processed_at',
                'failed_at',
                'error_message',
            ]);
        });
    }
};
