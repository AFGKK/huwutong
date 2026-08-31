<?php
/**
 * D-01 / D-02 配置就绪检查
 * 验证 Reverb 生产配置和 HTTPS 配置文件是否完整
 * 
 * 用法: php scripts/verify-production-config.php
 */

$passed = 0;
$failed = 0;

echo "══════════════════════════════════════════════════\n";
echo " 互物通 — 生产配置就绪检查 (D-01 / D-02)\n";
echo "══════════════════════════════════════════════════\n\n";

// ─── 1. 检查 .env 中 SANCTUM_STATEFUL_DOMAINS ───
echo "[1/4] .env 关键变量检查\n";
$env = file_get_contents(__DIR__ . '/../.env');

$checks = [
    'SANCTUM_STATEFUL_DOMAINS' => '/^SANCTUM_STATEFUL_DOMAINS=/m',
    'BROADCAST_CONNECTION=reverb' => '/^BROADCAST_CONNECTION=reverb/m',
    'REVERB_APP_ID' => '/^REVERB_APP_ID=.+/m',
    'REVERB_APP_KEY' => '/^REVERB_APP_KEY=.+/m',
    'VITE_REVERB_APP_KEY' => '/^VITE_REVERB_APP_KEY=.+/m',
];

foreach ($checks as $name => $pattern) {
    if (preg_match($pattern, $env)) {
        echo "  ✅ {$name}\n";
        $passed++;
    } else {
        echo "  ❌ {$name} — 未配置\n";
        $failed++;
    }
}

echo "\n  生产配置模板:\n";
$prodEnv = __DIR__ . '/../.env.production.example';
if (file_exists($prodEnv)) {
    echo "  ✅ .env.production.example 存在\n";
    $passed++;
} else {
    echo "  ❌ .env.production.example 缺失\n";
    $failed++;
}

// ─── 3. 检查 Nginx 配置文件 ───
echo "\n[3/4] Nginx 配置检查\n";

$nginxConf = __DIR__ . '/../deploy/nginx/production-https.conf';
if (file_exists($nginxConf)) {
    echo "  ✅ production-https.conf 存在\n";
    $passed++;
    
    $conf = file_get_contents($nginxConf);
    if (strpos($conf, 'proxy_pass http://huwutong_reverb') !== false) {
        echo "  ✅ WebSocket 反代配置正确\n";
        $passed++;
    }
    if (strpos($conf, 'proxy_set_header Upgrade $http_upgrade') !== false) {
        echo "  ✅ WebSocket Upgrade header 已配置\n";
        $passed++;
    }
} else {
    echo "  ❌ production-https.conf 缺失\n";
    $failed++;
}

// ─── 4. 检查部署脚本 ───
echo "\n[4/4] 部署脚本检查\n";

$scripts = [
    'scripts/apply-production-https.ps1' => 'HTTPS 一键应用脚本',
    'scripts/verify-production.php' => '生产综合验收脚本',
    'scripts/verify-production.ps1' => '远程探活脚本 (Win)',
    'scripts/verify-mobile-credentials.php' => '真机/小程序/商店凭据检查',
    'scripts/sync-miniprogram-config.php' => '小程序配置同步脚本',
    'docs/真机账号上架指南.md' => '真机账号上架指南',
    'scripts/start-reverb.bat' => 'Reverb 启动脚本 (Win)',
    'deploy/nginx/production-https.conf' => 'Nginx 生产配置',
    'docs/生产部署方案.md' => 'P0 生产部署方案文档',
];

foreach ($scripts as $path => $label) {
    if (file_exists(__DIR__ . '/../' . $path)) {
        echo "  ✅ {$label}\n";
        $passed++;
    } else {
        echo "  ❌ {$label} — 缺失\n";
        $failed++;
    }
}

// ─── 结果 ───
echo "\n══════════════════════════════════════════════════\n";
echo " 结果: {$passed} 通过, {$failed} 失败\n";
echo "══════════════════════════════════════════════════\n";

if ($failed > 0) {
    echo "\n⚠️  有 {$failed} 项未通过，如需生产部署请执行:\n";
    echo "   powershell -File scripts/apply-production-https.ps1 -Domain 88.huwutong.com\n";
    echo "   然后手动配置 deploy/nginx/production-https.conf\n";
    exit(1);
}

echo "\n✅ 生产配置验证通过！\n";
exit(0);
