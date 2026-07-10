<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\DbSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$issues = [];
$warnings = [];
$ok = [];

$driver = DB::connection()->getDriverName();
$connectionName = config('database.default');
$dbConfig = config("database.connections.{$connectionName}", []);
$dbName = $dbConfig['database'] ?? 'unknown';

echo "=== huwutong 数据库健康检查 ({$driver}) ===\n\n";

// 1. 连接
try {
    DB::connection()->getPdo();
    $ok[] = strtoupper($driver).' 连接正常 (数据库: '.$dbName.')';
} catch (Throwable $e) {
    $issues[] = '数据库连接失败: '.$e->getMessage();
    foreach ($issues as $i) {
        echo "❌ {$i}\n";
    }
    exit(1);
}

// 2. 表 & 迁移
$tableCount = count(DbSql::listTableNames());
$migRan = DB::table('migrations')->count();
$migFiles = count(glob(__DIR__.'/../database/migrations/*.php'));
$pending = $migFiles - $migRan;

$ok[] = "共 {$tableCount} 张表";
if ($pending > 0) {
    $warnings[] = "有 {$pending} 个迁移文件尚未执行 (已执行 {$migRan} / 文件 {$migFiles})，部分新功能表/字段可能缺失";
} else {
    $ok[] = "迁移已全部执行 ({$migRan})";
}

// 3. 用户 & 角色
$userCount = DB::table('users')->count();
$usersNoTenant = DB::table('users')->whereNull('tenant_id')->count();
$usersNoRole = DB::table('users as u')
    ->leftJoin('model_has_roles as mhr', function ($j) {
        $j->on('u.id', '=', 'mhr.model_id')->where('mhr.model_type', '=', 'App\\Models\\User');
    })
    ->whereNull('mhr.role_id')
    ->count();

$ok[] = "用户 {$userCount} 个";
if ($usersNoTenant > 0) {
    $warnings[] = "{$usersNoTenant} 个用户无 tenant_id";
}
if ($usersNoRole > 0) {
    $warnings[] = "{$usersNoRole} 个用户未分配角色";
}

// 4. site_settings
$settingCount = DB::table('site_settings')->count();
$dupKeys = DB::table('site_settings')->select('key')->groupBy('key')->havingRaw('count(*) > 1')->count();
$emptySwitch = DB::table('site_settings')->where('type', 'switch')->where(function ($q) {
    $q->whereNull('value')->orWhere('value', '');
})->count();
$invalidType = DB::table('site_settings')->whereNotIn('type', ['text', 'textarea', 'image', 'color', 'switch', 'select', 'password'])->count();

$ok[] = "site_settings {$settingCount} 项 / ".DB::table('site_settings')->distinct()->count('group').' 分组';
if ($dupKeys > 0) {
    $issues[] = "site_settings 有 {$dupKeys} 个重复 key";
}
if ($emptySwitch > 0) {
    $warnings[] = "site_settings 有 {$emptySwitch} 个 switch 值为空";
}
if ($invalidType > 0) {
    $warnings[] = "site_settings 有 {$invalidType} 项 type 非标准 (前端可能显示异常)";
}

// 5. 孤儿记录
if (Schema::hasTable('licenses') && Schema::hasTable('customers')) {
    $orphanLic = DB::table('licenses as l')
        ->leftJoin('customers as c', 'l.customer_id', '=', 'c.id')
        ->whereNotNull('l.customer_id')->whereNull('c.id')->count();
    if ($orphanLic > 0) {
        $issues[] = "licenses 表有 {$orphanLic} 条孤儿记录 (customer 不存在)";
    } else {
        $ok[] = 'licenses 外键引用正常';
    }
}
if (Schema::hasTable('users') && Schema::hasTable('tenants')) {
    $orphanUser = DB::table('users as u')
        ->leftJoin('tenants as t', 'u.tenant_id', '=', 't.id')
        ->whereNotNull('u.tenant_id')->whereNull('t.id')->count();
    if ($orphanUser > 0) {
        $issues[] = "users 表有 {$orphanUser} 条 tenant 引用无效";
    }
}

// 6. AI 同步
if (Schema::hasTable('llm_providers')) {
    $ssKey = DB::table('site_settings')->where('key', 'deepseek_api_key')->value('value');
    $lpKey = DB::table('llm_providers')->where('slug', 'deepseek')->value('api_key');
    if ($ssKey && $ssKey === $lpKey) {
        $ok[] = 'DeepSeek API Key 与 llm_providers 已同步';
    } elseif ($ssKey && $lpKey !== $ssKey) {
        $warnings[] = 'DeepSeek API Key 在 site_settings 与 llm_providers 不一致';
    } else {
        $warnings[] = 'DeepSeek API Key 未配置';
    }
}

// 7. 页面
if (Schema::hasTable('pages')) {
    $draft = DB::table('pages')->where('status', '!=', 'published')->count();
    if ($draft > 0) {
        $warnings[] = "pages 有 {$draft} 页未发布";
    } else {
        $ok[] = 'pages 均已发布';
    }
}

// 8. PG 全文索引
if ($driver === 'pgsql' && Schema::hasTable('conversation_messages')) {
    $fts = DB::select("
        SELECT indexname FROM pg_indexes
        WHERE schemaname = 'public' AND tablename = 'conversation_messages'
          AND indexname = 'conversation_messages_content_fts'
    ");
    if ($fts) {
        $ok[] = 'conversation_messages 全文索引就绪';
    } else {
        $warnings[] = '缺少 conversation_messages_content_fts，请运行 php artisan migrate';
    }
}

// 9. pgvector
if ($driver === 'pgsql') {
    try {
        $hasVector = DB::selectOne("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
        if ($hasVector) {
            $ok[] = 'pgvector 扩展已安装';
        } else {
            $warnings[] = 'pgvector 扩展未安装';
        }
    } catch (Throwable $e) {
        $warnings[] = 'pgvector 检查失败: '.$e->getMessage();
    }
}

// 10. 空配置表 (重置后正常)
$emptyConfig = [];
foreach (['sso_providers', 'cors_configs', 'feature_flags', 'announce_banners', 'webhook_endpoints'] as $t) {
    if (Schema::hasTable($t) && DB::table($t)->count() === 0) {
        $emptyConfig[] = $t;
    }
}
if (! empty($emptyConfig)) {
    $warnings[] = '以下配置表为空 (重置后正常，按需填写): '.implode(', ', $emptyConfig);
}

// 11. 编码 / 字符集
if ($driver === 'pgsql') {
    $encoding = DB::selectOne('SELECT pg_encoding_to_char(encoding) AS enc FROM pg_database WHERE datname = current_database()');
    if ($encoding?->enc) {
        $ok[] = "数据库编码 {$encoding->enc}";
    }
} elseif ($driver === 'mysql') {
    $charset = DB::select('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$dbName]);
    if (! empty($charset)) {
        $cs = $charset[0];
        $ok[] = "字符集 {$cs->DEFAULT_CHARACTER_SET_NAME} / {$cs->DEFAULT_COLLATION_NAME}";
    }
}

// 输出
echo "--- 正常 ---\n";
foreach ($ok as $o) {
    echo "✅ {$o}\n";
}

if (! empty($warnings)) {
    echo "\n--- 警告 (建议处理) ---\n";
    foreach ($warnings as $w) {
        echo "⚠️  {$w}\n";
    }
}

if (! empty($issues)) {
    echo "\n--- 错误 (需修复) ---\n";
    foreach ($issues as $i) {
        echo "❌ {$i}\n";
    }
}

echo "\n--- 汇总 ---\n";
echo '错误: '.count($issues).' | 警告: '.count($warnings).' | 正常: '.count($ok)."\n";

if (count($issues) === 0 && count($warnings) <= 2) {
    echo "\n结论: 数据库整体可用，无严重结构性问题。\n";
} elseif (count($issues) === 0) {
    echo "\n结论: 数据库可用，但有若干待处理项（见上方警告）。\n";
} else {
    echo "\n结论: 数据库存在问题，需要先修复错误项。\n";
}

exit(count($issues) > 0 ? 1 : 0);
