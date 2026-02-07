<?php

namespace App\Jobs\Webhooks;

use App\Models\WorkspaceWebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendWorkspaceWebhookDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $delivery = WorkspaceWebhookDelivery::query()
            ->with('endpoint.workspace')
            ->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->forceFill([
                'status' => 'skipped',
                'last_attempted_at' => now(),
            ])->save();

            return;
        }

        $delivery->forceFill([
            'attempt_count' => $delivery->attempt_count + 1,
            'last_attempted_at' => now(),
        ])->save();

        $timestamp = (string) now()->getTimestamp();

        $outboundPayload = [
            'id' => $delivery->id,
            'event' => $delivery->event_type,
            'created_at' => now()->toIso8601String(),
            'workspace_id' => $endpoint->workspace_id,
            'data' => is_array($delivery->payload) ? $delivery->payload : [],
        ];

        $jsonPayload = (string) json_encode($outboundPayload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $timestamp.'.'.$jsonPayload, (string) $endpoint->signing_secret);

        try {
            $response = Http::asJson()
                ->timeout(15)
                ->withHeaders([
                    'X-Workspace-Webhook-Event' => $delivery->event_type,
                    'X-Workspace-Webhook-Id' => (string) $delivery->id,
                    'X-Workspace-Webhook-Timestamp' => $timestamp,
                    'X-Workspace-Webhook-Signature' => sprintf('t=%s,v1=%s', $timestamp, $signature),
                ])
                ->post($endpoint->url, $outboundPayload);

            $responseBody = Str::limit($response->body(), 4000, '...');

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => 'delivered',
                    'response_status_code' => $response->status(),
                    'response_body' => $responseBody,
                    'last_error_message' => null,
                    'dispatched_at' => now(),
                ])->save();

                $endpoint->forceFill([
                    'last_dispatched_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                    'failure_count' => 0,
                ])->save();

                return;
            }

            $message = sprintf('Webhook endpoint returned HTTP %d.', $response->status());

            $delivery->forceFill([
                'status' => 'retrying',
                'response_status_code' => $response->status(),
                'response_body' => $responseBody,
                'last_error_message' => $message,
            ])->save();

            $endpoint->forceFill([
                'last_error_at' => now(),
                'last_error_message' => $message,
                'failure_count' => $endpoint->failure_count + 1,
            ])->save();

            throw new RuntimeException($message);
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            $delivery->forceFill([
                'status' => 'retrying',
                'last_error_message' => $exception->getMessage(),
            ])->save();

            $endpoint->forceFill([
                'last_error_at' => now(),
                'last_error_message' => $exception->getMessage(),
                'failure_count' => $endpoint->failure_count + 1,
            ])->save();

            throw $exception;
        }
    }

    /**
     * Determine retry delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = WorkspaceWebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $delivery->forceFill([
            'status' => 'failed',
            'last_error_message' => $exception?->getMessage() ?? $delivery->last_error_message,
        ])->save();
    }
}
