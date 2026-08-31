<?php

namespace App\Listeners;

use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\MarketingCampaignService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

/**
 * D-24: 营销事件监听器
 *
 * 监听用户注册/登录/订单完成等行为事件并触发自动化营销
 */
class MarketingEventSubscriber
{
    protected array $eventMap = [
        'user.registered' => Registered::class,
        'user.login' => Login::class,
    ];

    public function __construct(
        protected MarketingCampaignService $campaignService
    ) {}

    public function subscribe($events): void
    {
        $events->listen(
            Registered::class,
            [self::class, 'handleUserRegistered']
        );

        $events->listen(
            Login::class,
            [self::class, 'handleUserLogin']
        );
    }

    public function handleUserRegistered(Registered $event): void
    {
        try {
            $user = $event->user;
            if (!$user || !$user->tenant_id) {
                return;
            }
            $user->updateQuietly(['last_active_at' => now()]);
            $this->triggerEventCampaigns($user->tenant_id, 'user.registered', $user);
        } catch (\Throwable $e) {
            Log::warning('营销事件处理失败 [user.registered]: ' . $e->getMessage());
        }
    }

    public function handleUserLogin(Login $event): void
    {
        try {
            $user = $event->user;
            if (!$user || !$user->tenant_id) {
                return;
            }
            $user->updateQuietly(['last_active_at' => now()]);
            $this->triggerEventCampaigns($user->tenant_id, 'user.login', $user);
        } catch (\Throwable $e) {
            Log::warning('营销事件处理失败 [user.login]: ' . $e->getMessage());
        }
    }

    protected function triggerEventCampaigns(int $tenantId, string $triggerEvent, User $user): void
    {
        $campaigns = MarketingCampaign::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('type', 'multi_channel')
            ->get();

        foreach ($campaigns as $campaign) {
            $config = $campaign->audience_filter ?? [];
            $events = $config['trigger_events'] ?? [];
            if (in_array($triggerEvent, $events, true)) {
                $this->campaignService->sendToUser($campaign, $user);
            }
        }
    }
}
