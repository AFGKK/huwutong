<?php

namespace App\Listeners;

use App\Events\LicenseAboutToExpire;
use App\Models\MiniprogramExpirySubscription;
use App\Services\WechatMiniProgramService;
use Illuminate\Support\Facades\Log;

/**
 * A4: License 即将过期时，向已订阅的小程序用户发送订阅消息
 */
class SendMiniprogramExpirySubscribe
{
    public function __construct(
        protected WechatMiniProgramService $wechat,
    ) {}

    public function handle(LicenseAboutToExpire $event): void
    {
        $license = $event->license;
        $cfg = $this->wechat->getConfig();
        $templateId = $cfg['subscribe_template_id'] ?? '';

        if ($templateId === '') {
            return;
        }

        $subs = MiniprogramExpirySubscription::query()
            ->where('status', 'active')
            ->where(function ($q) use ($license) {
                $q->where('license_key', $license->license_key);
                if ($license->id) {
                    $q->orWhere('license_id', $license->id);
                }
            })
            ->get();

        if ($subs->isEmpty()) {
            return;
        }

        $productName = $license->product?->name ?? '互物通 License';
        $expiresAt = $license->expires_at?->format('Y年m月d日') ?? '—';
        $days = (string) $event->daysRemaining;

        // 模板字段名需与公众平台申请的模板一致；使用常见 thing/time/phrase 占位，可在后台配置后对照调整
        $data = [
            'thing1' => ['value' => mb_substr($productName, 0, 20)],
            'character_string2' => ['value' => mb_substr($license->license_key, 0, 32)],
            'time3' => ['value' => $expiresAt],
            'thing4' => ['value' => mb_substr("剩余{$days}天，请及时续费", 0, 20)],
        ];

        foreach ($subs as $sub) {
            $result = $this->wechat->sendSubscribeMessage(
                $sub->wechat_openid,
                $templateId,
                $data,
                'pages/index/index?key=' . urlencode($license->license_key)
            );

            if ($result['success']) {
                // 一次性订阅：发送成功后标记 sent，避免重复
                $sub->update([
                    'status' => 'sent',
                    'last_sent_at' => now(),
                ]);
            } else {
                Log::info('小程序过期订阅未送达', [
                    'subscription_id' => $sub->id,
                    'message' => $result['message'] ?? '',
                ]);
            }
        }
    }
}
