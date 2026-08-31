<?php

namespace App\Console\Commands;

use App\Services\FcmPushService;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * D-28: FCM 推送测试命令
 *
 * 用法 (干跑, 不实际发送):
 *   php artisan fcm:test-push 1 --dry-run
 *
 * 用法 (实际发送):
 *   php artisan fcm:test-push 1
 */
class FcmTestPushCommand extends Command
{
    protected $signature = 'fcm:test-push
        {user : 用户 ID 或 email}
        {--dry-run : 仅验证配置，不实际推送}
        {--title= : 推送标题 (默认: 测试通知)}
        {--body= : 推送正文 (默认: 这是一条来自 HWT 系统的测试推送)}
    ';

    protected $description = 'D-28: FCM 推送测试 — 向指定用户发送一条测试推送';

    public function handle(FcmPushService $fcm): int
    {
        $userIdentifier = $this->argument('user');

        // 查找用户
        $user = is_numeric($userIdentifier)
            ? User::find((int) $userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (!$user) {
            $this->error("未找到用户: {$userIdentifier}");
            return self::FAILURE;
        }

        $this->line("用户: {$user->name} ({$user->email})");
        $this->line("FCM Token: " . ($user->fcm_token ? substr($user->fcm_token, 0, 20) . '...' : '(无)'));

        if (!$user->fcm_token) {
            $this->warn('该用户未注册 FCM Token！请先在 Flutter App 登录并注册推送。');
            return self::FAILURE;
        }

        // 检查配置
        $projectId = config('services.fcm.project_id');
        $credentialsPath = config('services.fcm.credentials_path');

        $this->line('');
        $this->line("FCM 项目 ID: " . ($projectId ?: '(未配置)'));
        $this->line("凭证文件: " . ($credentialsPath && file_exists($credentialsPath) ? '✓ 存在' : '✗ 不存在'));

        if (!$projectId || !$credentialsPath || !file_exists($credentialsPath)) {
            $this->warn('FCM 配置不完整。请设置 FCM_PROJECT_ID 及 FCM_CREDENTIALS_PATH。');
            if ($this->option('dry-run')) {
                $this->line('干跑模式：配置检查完成。');
                return self::SUCCESS;
            }
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line('干跑模式：配置检查通过，未实际发送。');
            return self::SUCCESS;
        }

        // 发送推送
        $title = $this->option('title') ?: '测试通知';
        $body = $this->option('body') ?: '这是一条来自 HWT 系统的测试推送';
        $data = [
            'type' => 'test',
            'route' => '/',
            'category' => 'test',
            'timestamp' => now()->toIso8601String(),
        ];

        $this->line('');
        $this->line("发送推送...");
        $this->line("  标题: {$title}");
        $this->line("  正文: {$body}");

        $bar = $this->output->createProgressBar(1);
        $bar->start();

        $result = $fcm->sendToUser($user, $title, $body, $data);

        $bar->finish();
        $this->line('');

        if ($result['success']) {
            $this->info('✓ 推送发送成功！');
            if (!empty($result['name'])) {
                $this->line("消息 ID: {$result['name']}");
            }
            return self::SUCCESS;
        }

        $this->error('✗ 推送失败: ' . ($result['message'] ?? '未知错误'));
        if (!empty($result['should_remove'])) {
            $this->warn('提示: FCM Token 已失效，用户下次打开 App 时会重新注册。');
        }
        return self::FAILURE;
    }
}
