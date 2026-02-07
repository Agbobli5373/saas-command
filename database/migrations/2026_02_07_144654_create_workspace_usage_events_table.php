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
        Schema::create('workspace_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('occurred_at');
            $table->date('period_start');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'metric_key'], 'workspace_usage_events_workspace_metric_index');
            $table->index(['workspace_id', 'period_start'], 'workspace_usage_events_workspace_period_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_usage_events');
    }
};
