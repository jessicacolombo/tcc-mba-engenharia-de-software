<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSqsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $queueType;
    private int $sequenceId;
    private float $sentTimestamp;

    public function __construct(string $queueType, int $sequenceId)
    {
        $this->queueType = $queueType;
        $this->sequenceId = $sequenceId;
        $this->sentTimestamp = microtime(true);
    }

    public function messageGroup(): ?string
    {
        return (string) config('queue.connections.sqs-fifo.message_group_id', 'default_group');
    }

    public function handle(): void
    {
        $receivedTimestamp = microtime(true);
        $latencyMs = ($receivedTimestamp - $this->sentTimestamp) * 1000;
        $sqsJobId = $this->job?->getJobId();

        DB::table('queue_metrics')->insert([
            'queue_type' => $this->queueType,
            'sequence_id' => $this->sequenceId,
            'sqs_message_id' => $sqsJobId,
            'sent_timestamp' => $this->sentTimestamp,
            'received_timestamp' => $receivedTimestamp,
            'latency_ms' => $latencyMs,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
