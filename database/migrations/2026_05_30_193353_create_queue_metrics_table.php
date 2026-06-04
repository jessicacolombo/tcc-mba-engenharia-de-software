<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('queue_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('queue_type');
            $table->integer('sequence_id');
            $table->string('sqs_message_id')->nullable();
            $table->double('sent_timestamp', 15, 4);
            $table->double('received_timestamp', 15, 4)->nullable();
            $table->double('latency_ms', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_metrics');
    }
};
