<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use OpenTelemetry\API\Trace\TracerProviderInterface;

class ProcessSqsMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $queueType;
    private int $sequenceId;
    private string $batchId;
    private float $sentTimestamp;

    public function __construct(string $queueType, int $sequenceId, string $batchId)
    {
        $this->queueType = $queueType;
        $this->sequenceId = $sequenceId;
        $this->batchId = $batchId;
        $this->sentTimestamp = microtime(true);
    }

    public function messageGroup(): ?string
    {
        return (string) config('queue.connections.sqs-fifo.message_group_id', 'default_group');
    }

    public function handle(TracerProviderInterface $tracerProvider): void
    {
        $tracer = $tracerProvider->getTracer('io.opentelemetry.contrib.php');
        $span = $tracer->spanBuilder('Processar_Mensagem_SQS')->startSpan();
        $scope = $span->activate();

        try {
            $span->setAttribute('queue_type', $this->queueType);
            $span->setAttribute('sequence_id', $this->sequenceId);
            $span->setAttribute('batch_id', $this->batchId);

            $receivedTimestamp = microtime(true);
            $latencyMs = ($receivedTimestamp - $this->sentTimestamp) * 1000;
            $sqsJobId = $this->job?->getJobId();

            DB::table('queue_metrics')->insert([
                'batch_id' => $this->batchId,
                'queue_type' => $this->queueType,
                'sequence_id' => $this->sequenceId,
                'sqs_message_id' => $sqsJobId,
                'sent_timestamp' => $this->sentTimestamp,
                'received_timestamp' => $receivedTimestamp,
                'latency_ms' => $latencyMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
