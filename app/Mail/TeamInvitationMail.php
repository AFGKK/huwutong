<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * M2-129 租户团队邀请邮件
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantInvitation $invitation,
        public Tenant $tenant,
        public string $inviterName,
        public string $acceptUrl,
        public ?string $declineUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->inviterName} 邀请您加入 {$this->tenant->name} 团队 - HWT License",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $roleLabels = [
            'admin' => '管理员',
            'finance' => '财务',
            'developer' => '开发者',
            'readonly' => '只读',
        ];
        $roleLabel = $roleLabels[$this->invitation->role] ?? $this->invitation->role;
        $expiresAt = $this->invitation->expires_at
            ? $this->invitation->expires_at->format('Y-m-d H:i')
            : '7天后';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif; padding: 40px; background: #f0f2f5;">
    <div style="max-width: 560px; margin: 0 auto;">
        <div style="text-align: center; padding: 16px 0;">
            <span style="font-size: 20px; font-weight: 700; color: #409eff;">HWT License</span>
        </div>

        <div style="background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
            <div style="background: linear-gradient(135deg, #409eff, #337ecc); padding: 32px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 12px;">👋</div>
                <div style="font-size: 20px; color: #fff; font-weight: 600;">团队邀请</div>
            </div>

            <div style="padding: 32px;">
                <h2 style="margin: 0 0 16px; color: #1a1a1a; font-size: 18px;">您好！</h2>

                <p style="color: #555; line-height: 1.8; font-size: 14px;">
                    <strong style="color: #333;">{$this->inviterName}</strong> 邀请您加入
                    <strong style="color: #409eff;">{$this->tenant->name}</strong> 团队，
                    角色为 <strong style="color: #333;">{$roleLabel}</strong>。
                </p>

                {if($this->invitation->message)}
                <div style="background: #f5f7fa; border-radius: 8px; padding: 16px; margin: 16px 0;">
                    <p style="margin: 0; color: #666; font-size: 13px; line-height: 1.6;">
                        "{$this->invitation->message}"
                    </p>
                </div>
                {/if}

                <div style="background: #f0f7ff; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">
                    <p style="margin: 0; color: #666; font-size: 13px;">
                        📋 邀请有效期：{$expiresAt}
                    </p>
                </div>

                <div style="text-align: center; margin: 28px 0;">
                    <a href="{$this->acceptUrl}"
                       style="display: inline-block; padding: 14px 48px; background: #409eff; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: 500;">
                        接受邀请
                    </a>
                </div>

                {if($this->declineUrl)}
                <div style="text-align: center; margin: 12px 0 0;">
                    <a href="{$this->declineUrl}"
                       style="color: #999; font-size: 13px; text-decoration: none;">
                        拒绝邀请
                    </a>
                </div>
                {/if}
            </div>
        </div>

        <div style="text-align: center; padding: 24px 16px;">
            <p style="color: #999; font-size: 12px; margin: 0;">
                如果您不认识邀请人，请忽略此邮件。
            </p>
            <p style="color: #999; font-size: 12px; margin: 4px 0 0;">
                此邮件由 HWT License 系统自动发送。
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    public function attachments(): array
    {
        return [];
    }
}
