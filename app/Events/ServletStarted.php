<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ServletStarted implements ShouldBroadcastNow
{
    public function __construct(
        public string $servletName,
        public int $userId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("servlet.{$this->userId}.{$this->servletName}");
    }

    public function broadcastAs(): string
    {
        return 'started';
    }
}
