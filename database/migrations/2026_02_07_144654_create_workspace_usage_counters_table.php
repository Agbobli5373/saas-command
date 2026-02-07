<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspace_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key');
            $table->date('period_start');
            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'metric_key', 'period_start'], 'workspace_usage_counters_unique_period');
            $table->index(['workspace_id', 'period_start'], 'workspace_usage_counters_workspace_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_usage_counters');
    }
};
