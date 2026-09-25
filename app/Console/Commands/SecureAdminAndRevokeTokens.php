<?php

namespace App\Console\Commands;

use App\Models\ApiDocEndpoint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 优化方案 1.1 / 1.2 / 2.3：加固管理员密码、撤销测试 Token、修正 API 文档路径
 */
class SecureAdminAndRevokeTokens extends Command
{
    protected $signature = 'hwt:secure-admin
        {--password= : 新管理员密码（不传则生成随机强密码；与 --revoke-only 互斥）}
        {--email=admin@huwutong.com : 目标管理员邮箱}
        {--revoke-only : 仅撤销测试残留 token，不改密码}
        {--revoke-all-tokens : 撤销该用户全部 personal access tokens}
        {--dry-run : 仅统计将清理的 token，不实际删除}
        {--fix-api-docs : 将文档中的 /api/license/query 纠正为 /api/license/public-lookup}';

    protected $description = 'Rotate admin password, enforce admin MFA policy, revoke leftover test API tokens';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $user = User::where('email', $email)->first();
        $revokeOnly = (bool) $this->option('revoke-only');
        $dryRun = (bool) $this->option('dry-run');

        if (! $user && ! $revokeOnly) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        if ($user && ! $revokeOnly) {
            $password = (string) $this->option('password');
            if ($password === '' || in_array(strtolower($password), ['admin123', 'password', '12345678'], true)) {
                $password = 'Hwt!' . Str::password(14, symbols: true);
                $this->warn('Generated strong password (save it now):');
                $this->line($password);
            }

            if (! $dryRun) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'password_changed_at' => now(),
                ])->save();

                if ($user->tenant_id) {
                    Tenant::where('id', $user->tenant_id)->update([
                        'mfa_policy' => 'required_for_admin',
                    ]);
                }
                $this->info("Password rotated for {$email}");
                $this->info('Tenant MFA policy set to required_for_admin');
            } else {
                $this->warn('[dry-run] would rotate password and set MFA policy');
            }
        }

        $query = PersonalAccessToken::query();
        if ($this->option('revoke-all-tokens') && $user) {
            $query->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id);
        } else {
            $query->where(function ($q) {
                $q->where('name', 'like', '%test%')
                    ->orWhere('name', 'like', '%smoke%')
                    ->orWhere('name', 'like', '%cn-test%')
                    ->orWhere('name', 'like', 'wt_%')
                    ->orWhere('name', 'like', '%repro%');
            })->orWhereBetween('id', [15040, 15050]);
        }

        $count = (clone $query)->count();
        if ($dryRun) {
            $this->warn("[dry-run] would revoke {$count} tokens");
        } else {
            $revoked = $query->delete();
            $this->info("Revoked tokens: {$revoked}");
        }

        $fixedDocs = 0;
        if ($this->option('fix-api-docs') || true) {
            $endpoints = ApiDocEndpoint::query()
                ->where('path', 'like', '%/license/query%')
                ->get();

            foreach ($endpoints as $endpoint) {
                $newPath = str_replace('/license/query', '/license/public-lookup', $endpoint->path);
                if ($newPath === $endpoint->path) {
                    continue;
                }
                if (! $dryRun) {
                    $endpoint->update(['path' => $newPath]);
                }
                $fixedDocs++;
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "API docs paths fixed: {$fixedDocs}");

        return self::SUCCESS;
    }
}
