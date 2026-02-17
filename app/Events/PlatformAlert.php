<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PlatformAlert Event.
 *
 * Broadcasts platform-wide announcements to Super Admins.
 * Used for system-level notifications and alerts.
 */
final class PlatformAlert implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $message  Alert message
     * @param  string  $level  Alert level: 'info', 'warning', 'error', 'success'
     * @param  array<string, mixed>|null  $metadata  Additional metadata
     */
    public function __construct(
        public string $message,
        public string $level = 'info',
        public ?array $metadata = null,
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Public channel for platform announcements (Super Admins only)
        return [
            new Channel('platform-announcements'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'platform.alert';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
