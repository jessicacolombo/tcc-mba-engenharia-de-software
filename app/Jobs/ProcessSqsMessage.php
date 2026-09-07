<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function sentTimestamp(): float
    {
        return $this->sentTimestamp;
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
            $attempt = $this->job?->attempts() ?? 1;
            $this->publishMetric('processed', $attempt, $receivedTimestamp, microtime(true));
        } catch (Throwable $exception) {
            $this->publishMetric('retrying', $this->job?->attempts() ?? 1, microtime(true), null, $exception->getMessage());

            throw $exception;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->publishMetric(
            'failed',
            $this->job?->attempts() ?? 0,
            microtime(true),
            null,
            $exception->getMessage(),
            microtime(true),
        );
    }

    private function publishMetric(
        string $status,
        int $attempt,
        float $receivedTimestamp,
        ?float $processedAt = null,
        ?string $errorMessage = null,
        ?float $failedAt = null,
    ): void {
        try {
            PersistQueueMetric::dispatch(
                $status,
                $this->queueType,
                $this->sequenceId,
                $this->batchId,
                $this->sentTimestamp,
                $attempt,
                $receivedTimestamp,
                $processedAt,
                $failedAt,
                $errorMessage,
                $this->job?->getJobId(),
            )->onConnection('database')->onQueue('metrics');
        } catch (Throwable $metricException) {
            Log::error('Falha ao publicar métrica da fila.', [
                'batch_id' => $this->batchId,
                'sequence_id' => $this->sequenceId,
                'error' => $metricException->getMessage(),
            ]);
        }
    }
}
