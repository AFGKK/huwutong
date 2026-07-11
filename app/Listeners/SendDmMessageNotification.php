<?php

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Services\UserChatNotificationService;

class SendDmMessageNotification
{
    public function __construct(
        protected UserChatNotificationService $notificationService,
    ) {}

    public function handle(ChatMessageSent $event): void
    {
        $this->notificationService->notifyNewMessage(
            $event->message,
            $event->participantIds,
        );
    }
}
