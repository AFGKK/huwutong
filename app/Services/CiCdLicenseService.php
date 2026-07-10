<?php

namespace App\Services;

use App\Models\CiCdToken;
use App\Models\CiCdUsageLog;
use App\Models\License;
use Illuminate\Support\Facades\Log;

/**
 * CI/CD 自动授权服务
 * 
 * 开发者可在 CI/CD 流水线中通过令牌自动获取/激活 License
 * 支持: GitHub Actions / GitLab CI / Jenkins / 通用 curl 脚本
 */
class CiCdLicenseService
{
    /**
     * 通过 CI 令牌获取 License
     */
    public function fetchLicense(string $token, array $context = []): array
    {
        $ciToken = CiCdToken::where('token', $token)->first();

        if (!$ciToken || !$ciToken->isValid()) {
            return ['success' => false, 'error' => 'Invalid or expired CI token'];
        }

        // 检查作用域
        if (!$ciToken->hasScope('license_read') && !$ciToken->hasScope('all')) {
            return ['success' => false, 'error' => 'Token missing license_read scope'];
        }

        // 查询 License
        $query = License::where('tenant_id', $ciToken->tenant_id);

        // 如果令牌限制了 License ID
        $licenseIds = $ciToken->allowed_license_ids;
        if (!empty($licenseIds)) {
            $query->whereIn('id', $licenseIds);
        }

        $licenses = $query->with('product:id,name,slug')
            ->orderBy('created_at')
            ->get()
            ->map(function ($lic) {
                return [
                    'id' => $lic->id,
                    'license_key' => $lic->license_key,
                    'product' => $lic->product?->name ?? 'N/A',
                    'status' => $lic->status,
                    'expires_at' => $lic->expires_at?->toIso8601String(),
                    'seats' => $lic->seats ?? 1,
                ];
            });

        // 记录使用
        $this->logUsage($ciToken, 'license_fetch', $context);
        $ciToken->increment('use_count');
        $ciToken->update(['last_used_at' => now()]);

        return [
            'success' => true,
            'data' => [
                'licenses' => $licenses,
                'total' => $licenses->count(),
                'token_name' => $ciToken->name,
            ],
        ];
    }

    /**
     * 通过 CI 令牌激活 License
     */
    public function activateLicense(string $token, string $licenseKey, array $context = []): array
    {
        $ciToken = CiCdToken::where('token', $token)->first();

        if (!$ciToken || !$ciToken->isValid()) {
            return ['success' => false, 'error' => 'Invalid or expired CI token'];
        }

        if (!$ciToken->hasScope('license_activate') && !$ciToken->hasScope('all')) {
            return ['success' => false, 'error' => 'Token missing license_activate scope'];
        }

        $license = License::where('license_key', $licenseKey)
            ->where('tenant_id', $ciToken->tenant_id)
            ->first();

        if (!$license) {
            return ['success' => false, 'error' => 'License not found'];
        }

        if ($license->status !== 'active') {
            return ['success' => false, 'error' => "License status is '{$license->status}', cannot activate"];
        }

        // 记录使用
        $this->logUsage($ciToken, 'license_activate', array_merge($context, [
            'license_key' => $licenseKey,
            'license_id' => $license->id,
        ]));
        $ciToken->increment('use_count');
        $ciToken->update(['last_used_at' => now()]);

        return [
            'success' => true,
            'message' => 'License is valid for CI/CD use',
            'data' => [
                'license_key' => $license->license_key,
                'product' => $license->product?->name ?? 'N/A',
                'expires_at' => $license->expires_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * 令牌信息
     */
    public function tokenInfo(string $token): array
    {
        $ciToken = CiCdToken::where('token', $token)->first();

        if (!$ciToken) {
            return ['success' => false, 'error' => 'Token not found'];
        }

        return [
            'success' => true,
            'data' => [
                'name' => $ciToken->name,
                'scopes' => $ciToken->scopes,
                'status' => $ciToken->status,
                'is_valid' => $ciToken->isValid(),
                'use_count' => $ciToken->use_count,
                'max_uses' => $ciToken->max_uses,
                'created_at' => $ciToken->created_at->toIso8601String(),
                'expires_at' => $ciToken->expires_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * 检测 CI 提供商
     */
    public static function detectCiProvider(): string
    {
        if (getenv('GITHUB_ACTIONS') === 'true') return 'github_actions';
        if (getenv('GITLAB_CI') === 'true') return 'gitlab_ci';
        if (getenv('JENKINS_HOME') || getenv('JENKINS_URL')) return 'jenkins';
        if (getenv('CI') === 'true') return 'other';
        return 'unknown';
    }

    /**
     * 获取 CI 上下文信息
     */
    public static function getCiContext(): array
    {
        return [
            'ci_provider' => self::detectCiProvider(),
            'repository' => getenv('GITHUB_REPOSITORY') ?: getenv('CI_REPOSITORY_URL') ?: getenv('GIT_URL') ?: '',
            'workflow' => getenv('GITHUB_WORKFLOW') ?: getenv('CI_JOB_NAME') ?: getenv('JOB_NAME') ?: '',
            'runner_os' => getenv('RUNNER_OS') ?: getenv('CI_SERVER_NAME') ?: PHP_OS,
        ];
    }

    /**
     * GitHub Actions 获取 License 的 YAML 示例
     */
    public static function getGitHubActionsExample(): string
    {
        return <<<'YAML'
name: CI License Setup
on: [push, workflow_dispatch]
jobs:
  setup-license:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Fetch License
        id: license
        run: |
          RESPONSE=$(curl -s -H "Authorization: Bearer ${{ secrets.HWT_CI_TOKEN }}" \
            "https://your-domain.com/api/ci/license/fetch")
          echo "LICENSE_KEY=$(echo $RESPONSE | jq -r '.data.licenses[0].license_key')" >> $GITHUB_OUTPUT

      - name: Use License
        run: |
          echo "Using license: ${{ steps.license.outputs.LICENSE_KEY }}"
          # your-activation-command --license-key "${{ steps.license.outputs.LICENSE_KEY }}"
YAML;
    }

    /**
     * GitLab CI 获取 License 的 YAML 示例
     */
    public static function getGitLabCiExample(): string
    {
        return <<<'YAML'
stages:
  - license

fetch-license:
  stage: license
  image: curlimages/curl:latest
  script:
    - |
      RESPONSE=$(curl -s -H "Authorization: Bearer ${HWT_CI_TOKEN}" \
        "https://your-domain.com/api/ci/license/fetch")
      LICENSE_KEY=$(echo $RESPONSE | jq -r '.data.licenses[0].license_key')
      echo "License: $LICENSE_KEY"
      # your-activation-command --license-key "$LICENSE_KEY"
  variables:
    HWT_CI_TOKEN: $HWT_CI_TOKEN  # Set in CI/CD Settings > Variables
YAML;
    }

    /**
     * Jenkins 获取 License 的 Pipeline 示例
     */
    public static function getJenkinsExample(): string
    {
        return <<<'GROOVY'
pipeline {
    agent any
    environment {
        HWT_CI_TOKEN = credentials('hwt-ci-token')
    }
    stages {
        stage('Fetch License') {
            steps {
                script {
                    def response = sh(script: """
                        curl -s -H "Authorization: Bearer ${HWT_CI_TOKEN}" \
                        "https://your-domain.com/api/ci/license/fetch"
                    """, returnStdout: true).trim()
                    def licenseKey = sh(script: "echo '${response}' | jq -r '.data.licenses[0].license_key'", returnStdout: true).trim()
                    echo "License: ${licenseKey}"
                    // your-activation-command --license-key "${licenseKey}"
                }
            }
        }
    }
}
GROOVY;
    }

    /**
     * 通用 curl 脚本
     */
    public static function getCurlExample(): string
    {
        return <<<'BASH'
#!/bin/bash
# 在 CI/CD 环境变量中设置 HWT_CI_TOKEN

HWT_API="https://your-domain.com/api/ci"

# 1. 获取 License
LICENSE_RESPONSE=$(curl -s -H "Authorization: Bearer ${HWT_CI_TOKEN}" \
  "${HWT_API}/license/fetch")

LICENSE_KEY=$(echo $LICENSE_RESPONSE | jq -r '.data.licenses[0].license_key')

if [ "$LICENSE_KEY" != "null" ] && [ -n "$LICENSE_KEY" ]; then
  echo "License obtained: ${LICENSE_KEY}"
  # 在这里使用 License
  # your-build-command --license-key "${LICENSE_KEY}"
else
  echo "Failed to obtain license"
  echo "Response: ${LICENSE_RESPONSE}"
  exit 1
fi
BASH;
    }

    /**
     * 记录使用日志
     */
    protected function logUsage(CiCdToken $token, string $action, array $context): void
    {
        try {
            CiCdUsageLog::create([
                'ci_cd_token_id' => $token->id,
                'action' => $action,
                'ci_provider' => $context['ci_provider'] ?? self::detectCiProvider(),
                'repository' => $context['repository'] ?? '',
                'workflow' => $context['workflow'] ?? '',
                'runner_os' => $context['runner_os'] ?? PHP_OS,
                'ip_address' => request()->ip() ?? '',
                'details' => $context,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log CI/CD usage', ['error' => $e->getMessage()]);
        }
    }
}
