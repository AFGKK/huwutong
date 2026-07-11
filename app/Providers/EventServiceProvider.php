<?php

namespace App\Providers;

use App\Events\LicenseAboutToExpire;
use App\Events\LicenseStatusChanged;
use App\Listeners\DispatchLicenseEvent;
use App\Listeners\LogLicenseStatusChanged;
use App\Listeners\SyncLicenseFeatureFlags;
use App\Listeners\InvalidateSdkCacheOnLicenseChange;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LicenseStatusChanged::class => [
            LogLicenseStatusChanged::class,
            DispatchLicenseEvent::class,
            SyncLicenseFeatureFlags::class,
            InvalidateSdkCacheOnLicenseChange::class,
        ],
        LicenseAboutToExpire::class => [
            DispatchLicenseEvent::class,
        ],
        \App\Events\OaArticlePublished::class => [
            \App\Listeners\AiCommentOnArticlePublished::class,
            \App\Listeners\NotifyFollowersOnArticlePublished::class,
        ],
        \App\Events\ChannelMessageSent::class => [
            \App\Listeners\AiModerateChannelMessage::class,
        ],
        \App\Events\ChatMessageSent::class => [
            \App\Listeners\SendDmMessageNotification::class,
        ],
        \App\Events\OaSubmissionCreated::class => [
            \App\Listeners\AiReviewSubmission::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
