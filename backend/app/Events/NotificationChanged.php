<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ?int $tenantId,
        public readonly int $userId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        $tenant = $this->tenantId ?? 'platform';

        return [new PrivateChannel("tenant.{$tenant}.user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notifications.changed';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
