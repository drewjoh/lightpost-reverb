<?php

namespace App\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;

class ReverbEventTypes
{
    use Sampling;

    public array $listen = [
        MessageSent::class,
        MessageReceived::class,
    ];

    public function __construct(protected Pulse $pulse)
    {
    }

    public function record(MessageSent|MessageReceived $event): void
    {
        if (! $this->shouldSample()) {
            return;
        }

        $payload = json_decode($event->message, true);
        $eventName = is_array($payload) ? ($payload['event'] ?? null) : null;

        $category = match (true) {
            $eventName === null => 'unknown',
            str_starts_with($eventName, 'pusher:error') => 'pusher:error',
            str_starts_with($eventName, 'pusher:') => $eventName,
            str_starts_with($eventName, 'client-') => 'client-event',
            default => 'app-event',
        };

        $direction = $event instanceof MessageSent ? 'sent' : 'received';

        $this->pulse->record(
            type: 'reverb_event_type:'.$direction,
            key: $category,
            timestamp: CarbonImmutable::now()->getTimestamp(),
        )->count();
    }
}
