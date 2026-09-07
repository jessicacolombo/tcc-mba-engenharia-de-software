<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSqsMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\TracerProviderInterface;

class BenchmarkQueueCommand extends Command
{
    protected $signature = 'sqs:benchmark {type} {--total=50}';
    protected $description = 'Dispatch messages to SQS queue to benchmark latency and order';

    public function handle(TracerProviderInterface $tracerProvider): int
    {
        $tracer = $tracerProvider->getTracer('io.opentelemetry.contrib.php');
        $span = $tracer->spanBuilder('Disparar_Lote_Mensagens')->startSpan();
        $scope = $span->activate();

        try {
            $type = $this->argument('type');
            $total = (int) $this->option('total');

            if (!in_array($type, ['standard', 'fifo'])) {
                $this->error('Invalid type. Choose "standard" or "fifo".');
                return Command::FAILURE;
            }

            $connection = $type === 'fifo' ? 'sqs-fifo' : 'sqs';
            $queueName = config('queue.connections.' . $connection . '.queue');

            if (empty($queueName)) {
                $this->error("Queue name for [{$type}] is not configured in config/queue.php.");
                return Command::FAILURE;
            }

            $isFifoQueueName = str_ends_with((string) $queueName, '.fifo');

            if ($type === 'fifo' && !$isFifoQueueName) {
                $this->error("FIFO benchmark requires a queue ending with .fifo. Current queue: [{$queueName}].");
                return Command::FAILURE;
            }

            if ($type === 'standard' && $isFifoQueueName) {
                $this->error("Standard benchmark cannot use a FIFO queue name. Current queue: [{$queueName}].");
                return Command::FAILURE;
            }

            $this->info("Starting to dispatch {$total} messages to [{$type}] queue in batches...");
            $this->output->progressStart($total);

            $batchId = (string) Str::uuid();
            $startedAt = microtime(true);

            DB::table('queue_benchmark_runs')->insert([
                'batch_id' => $batchId,
                'queue_type' => $type,
                'expected_messages' => $total,
                'status' => 'dispatching',
                'started_at' => $startedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $chunkSize = 500;
            $chunk = [];

            for ($i = 1; $i <= $total; $i++) {
                $message = new ProcessSqsMessage($type, $i, $batchId);

                $chunk[] = $message;

                if (count($chunk) === $chunkSize || $i === $total) {
                    Queue::connection($connection)->bulk($chunk, '', $queueName);
                    $chunk = [];
                }

                $this->output->progressAdvance();
            }

            $this->output->progressFinish();
            DB::table('queue_benchmark_runs')
                ->where('batch_id', $batchId)
                ->update([
                    'status' => 'dispatched',
                    'dispatched_at' => microtime(true),
                    'updated_at' => now(),
                ]);

            $this->info("\nAll {$total} messages dispatched successfully.");
            $this->info("Batch ID: {$batchId}");
        } finally {
            $scope->detach();
            $span->end();
        }

        return Command::SUCCESS;
    }
}
