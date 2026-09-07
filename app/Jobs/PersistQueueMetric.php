<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PersistQueueMetric implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $status,
        private string $queueType,
        private int $sequenceId,
        private string $batchId,
        private float $sentTimestamp,
        private int $attempt,
        private float $receivedTimestamp,
        private ?float $processedAt = null,
        private ?float $failedAt = null,
        private ?string $errorMessage = null,
        private ?string $sqsMessageId = null,
    ) {
    }

    public function handle(): void
    {
        $existing = DB::table('queue_metrics')
            ->where('batch_id', $this->batchId)
            ->where('queue_type', $this->queueType)
            ->where('sequence_id', $this->sequenceId)
            ->first();

        $isDuplicate = $existing?->status === 'processed';
        $processingCount = ($existing?->processing_count ?? 0) + 1;
        $duplicateCount = ($existing?->duplicate_count ?? 0) + ($isDuplicate ? 1 : 0);
        $now = now();

        DB::table('queue_metrics')->updateOrInsert(
            [
                'batch_id' => $this->batchId,
                'queue_type' => $this->queueType,
                'sequence_id' => $this->sequenceId,
            ],
            [
                'sqs_message_id' => $this->sqsMessageId,
                'sent_timestamp' => $this->sentTimestamp,
                'received_timestamp' => $this->receivedTimestamp,
                'latency_ms' => ($this->receivedTimestamp - $this->sentTimestamp) * 1000,
                'status' => $this->status,
                'attempts' => $this->attempt,
                'processing_count' => $processingCount,
                'duplicate_count' => $duplicateCount,
                'processed_at' => $this->processedAt,
                'failed_at' => $this->failedAt,
                'error_message' => $this->errorMessage,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ],
        );

        DB::table('queue_metric_attempts')->updateOrInsert(
            [
                'batch_id' => $this->batchId,
                'queue_type' => $this->queueType,
                'sequence_id' => $this->sequenceId,
                'attempt' => $this->attempt,
            ],
            [
                'status' => $this->status,
                'started_at' => $this->receivedTimestamp,
                'finished_at' => $this->processedAt ?? $this->failedAt ?? $this->receivedTimestamp,
                'error_message' => $this->errorMessage,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }
}