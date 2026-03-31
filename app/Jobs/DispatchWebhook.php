<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public WebhookEndpoint $endpoint,
        public array $payload
    ) {}

    public function handle(): void
    {
        $json = json_encode($this->payload);

        $headers = [
            'Content-Type' => 'application/json',
            'X-ProjectHub-Event' => 'task.ready',
        ];

        if ($this->endpoint->secret) {
            $sig = hash_hmac('sha256', $json, $this->endpoint->secret);
            $headers['X-ProjectHub-Signature'] = "sha256={$sig}";
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post($this->endpoint->url, $this->payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Webhook delivery failed with HTTP {$response->status()}.");
        }
    }
}
