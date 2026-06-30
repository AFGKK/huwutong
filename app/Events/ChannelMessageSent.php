<?php

namespace App\Events;

use App\Models\ChannelMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChannelMessage $message;

    public function __construct(ChannelMessage $message)
    {
        $this->message = $message;
    }
}
