<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Log;

class OutboxPublisherJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $events = OutboxEvent::where('status', 'pending')->take(50)->get();

        foreach ($events as $event) {
            try {
                // Here we would publish to RabbitMQ or Kafka
                // e.g., Amqp::publish('events_exchange', $event->payload);
                Log::info('Published event: ' . $event->event_type, $event->payload);

                $event->status = 'published';
                $event->published_at = now();
                $event->save();
            } catch (\Exception $e) {
                $event->status = 'failed';
                $event->attempts += 1;
                $event->save();
                Log::error('Failed to publish event', ['id' => $event->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
