<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('queue_benchmark_runs', function (Blueprint $table) {
            $table->double('finished_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_benchmark_runs', function (Blueprint $table) {
            $table->dropColumn('finished_at');
        });
    }
};
