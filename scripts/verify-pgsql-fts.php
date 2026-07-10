<?php

/**
 * 验证 PostgreSQL 全文搜索索引与查询
 *
 * 用法: php scripts/verify-pgsql-fts.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\DbSql;
use Illuminate\Support\Facades\DB;

$driver = DB::connection()->getDriverName();
echo "DB driver: {$driver}\n";

if ($driver !== 'pgsql') {
    echo "跳过：当前非 PostgreSQL 环境\n";
    exit(0);
}

$indexes = DB::select("
    SELECT indexname, indexdef
    FROM pg_indexes
    WHERE schemaname = 'public'
      AND tablename IN ('conversation_messages', 'kb_articles')
      AND indexdef ILIKE '%to_tsvector%'
    ORDER BY tablename, indexname
");

echo "\n全文索引 (".count($indexes)."):\n";
foreach ($indexes as $idx) {
    echo "  - {$idx->indexname}\n";
}

$expected = ['conversation_messages_content_fts'];
$found = array_map(fn ($i) => $i->indexname, $indexes);
$missing = array_diff($expected, $found);
if ($missing) {
    echo "\n⚠️  缺少索引: ".implode(', ', $missing)."\n";
    echo "   请运行: php artisan migrate --force\n";
} else {
    echo "\n✅ conversation_messages 全文索引就绪\n";
}

if (DB::getSchemaBuilder()->hasTable('conversation_messages')) {
    try {
        $count = DB::table('conversation_messages')
            ->whereRaw(DbSql::fullTextMatch('content'), [DbSql::fullTextBindValue('test*')])
            ->count();
        echo "✅ 全文查询探测成功 (matches={$count})\n";
    } catch (Throwable $e) {
        echo "❌ 全文查询失败: {$e->getMessage()}\n";
        exit(1);
    }
}

echo "\n完成.\n";
