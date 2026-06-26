<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ServletLog implements ShouldBroadcastNow
{
    public function __construct(
        public string $servletName,
        public int $userId,
        public string $message,
        public int $level = 6,
        public int $code = 0,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("servlet.{$this->userId}.{$this->servletName}");
    }

    public function broadcastAs(): string
    {
        return 'log';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'code' => $this->code,
            'time' => now()->format('H:i:s'),
        ];
    }
}
