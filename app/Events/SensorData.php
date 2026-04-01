<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SensorData implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $raw;
    public $filtered;
    /**
     * Create a new event instance.
     */
    public function __construct($raw, $filtered = null)
    {
        $this->raw = $raw;
        $this->filtered = $filtered;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('sensor-data'),
        ];
    }

    public function broadcastAs()
    {
        return 'SensorData';
    }

    public function broadcastWith(): array
    {
        return [
            'raw' => $this->raw,
            'filtered' => $this->filtered,
        ];
    }
}
