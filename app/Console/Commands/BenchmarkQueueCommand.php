<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessSqsMessage;

class BenchmarkQueueCommand extends Command
{
    protected $signature = 'sqs:benchmark {type} {--total=50}';
    protected $description = 'Dispatch messages to SQS queue to benchmark latency and order';

    public function handle(): int
    {
        $type = $this->argument('type');
        $total = (int) $this->option('total');

        if (!in_array($type, ['standard', 'fifo'])) {
            $this->error('Invalid type. Choose "standard" or "fifo".');
            return Command::FAILURE;
        }

        $connection = $type === 'fifo' ? 'sqs-fifo' : 'sqs';

        $queueName = $type === 'fifo'
            ? config('queue.connections.sqs-fifo.queue')
            : config('queue.connections.sqs.queue');

        if (empty($queueName)) {
            $this->error("Queue name for [{$type}] is not configured in config/queue.php.");
            return Command::FAILURE;
        }

        $isFifoQueueName = str_ends_with((string) $queueName, '.fifo');

        if ($type === 'fifo' && ! $isFifoQueueName) {
            $this->error("FIFO benchmark requires a queue ending with .fifo. Current queue: [{$queueName}].");
            return Command::FAILURE;
        }

        if ($type === 'standard' && $isFifoQueueName) {
            $this->error("Standard benchmark cannot use a FIFO queue name. Current queue: [{$queueName}].");
            return Command::FAILURE;
        }

        $this->info("Starting to dispatch {$total} messages to [{$type}] queue...");

        for ($i = 1; $i <= $total; $i++) {
            ProcessSqsMessage::dispatch($type, $i)
                ->onConnection($connection)
                ->onQueue($queueName);
        }

        $this->info("All {$total} messages dispatched successfully.");
        return Command::SUCCESS;
    }
}
